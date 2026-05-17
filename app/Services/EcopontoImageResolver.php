<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * Serviço de resolução de imagens para ecopontos.
 * Busca imagens específicas por local e garante armazenamento seguro.
 */
class EcopontoImageResolver
{
    private const TIMEOUT_SECONDS = 15;
    private const MAX_FILE_SIZE = 5242880; // 5 MB
    private const ALLOWED_MIMES = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
    private const STORAGE_DISK = 'public';
    private const STORAGE_PATH = 'ecopontos';

    /**
     * Busca imagem para um ecoponto específico por query de busca.
     * Retorna URL da imagem ou null se não encontrada.
     *
     * @param array $searchContext Contexto para busca: nome, bairro, endereco, cidade
     * @return array|null Array com ['url' => '...', 'source_url' => '...', 'mime' => '...'] ou null
     */
    public function resolve(array $searchContext): ?array
    {
        try {
            $query = $this->buildSearchQuery($searchContext);
            $imageData = $this->fetchImage($query);

            if (!$imageData) {
                Log::info("Nenhuma imagem encontrada para ecoponto", ['context' => $searchContext]);
                return null;
            }

            return $imageData;
        } catch (Throwable $e) {
            Log::warning("Erro ao resolver imagem de ecoponto", [
                'context' => $searchContext,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Constrói query de busca contextual por local.
     *
     * Exemplo: "DB Ponta Negra Avenida Coronel Teixeira Manaus fachada"
     */
    private function buildSearchQuery(array $context): string
    {
        $parts = array_filter([
            $context['nome'] ?? null,
            $context['bairro'] ?? null,
            $context['endereco'] ?? null,
            $context['cidade'] ?? null,
            'fachada', // sempre adiciona para contexto de imagem de frente
        ]);

        return trim(implode(' ', $parts));
    }

    /**
     * Busca imagem usando Bing Image Search (API pública com rate limit cuidadoso).
     * Em produção, seria substituída por serviço pago (Google, Bing, etc).
     */
    private function fetchImage(string $query): ?array
    {
        if ($query === '') {
            return null;
        }

        Log::debug("Tentativa de busca de imagem", ['query' => $query]);

        // Fonte sem chave, baseada em query (evita fallback global estático)
        $sourceUrl = 'https://source.unsplash.com/1600x900/?' . rawurlencode($query);

        return [
            'source_url' => $sourceUrl,
            'query' => $query,
        ];
    }

    /**
     * Baixa imagem de uma URL remota com validações de segurança.
     *
     * @param string $sourceUrl URL da imagem
     * @param string $localPath Caminho local de armazenamento (ex: ecopontos/db-ponta-negra.webp)
     * @return array|null Array com ['path' => '...', 'hash' => '...', 'mime' => '...', 'width' => 0, 'height' => 0] ou null
     */
    public function downloadAndValidate(string $sourceUrl, string $localPath): ?array
    {
        try {
            // Validação de URL
            if (!$this->isValidSourceUrl($sourceUrl)) {
                Log::warning("URL de fonte inválida", ['url' => $sourceUrl]);
                return null;
            }

            // Download com timeout e retry controlado
            $response = Http::withoutVerifying()
                ->timeout(self::TIMEOUT_SECONDS)
                ->retry(2, 500, null, false)
                ->withHeaders([
                    'User-Agent' => 'BoitaTech Ecopontos Image Processor/1.0',
                ])
                ->get($sourceUrl);

            if (!$response->successful()) {
                Log::warning("Falha ao baixar imagem", [
                    'url' => $sourceUrl,
                    'status' => $response->status(),
                ]);
                return null;
            }

            // Validação MIME
            $mime = strtolower($response->header('Content-Type') ?? '');
            if (!$this->isValidMime($mime)) {
                Log::warning("MIME type inválido", ['mime' => $mime, 'url' => $sourceUrl]);
                return null;
            }

            // Validação de tamanho
            $content = $response->body();
            if (strlen($content) > self::MAX_FILE_SIZE) {
                Log::warning("Arquivo muito grande", [
                    'size' => strlen($content),
                    'max' => self::MAX_FILE_SIZE,
                    'url' => $sourceUrl,
                ]);
                return null;
            }

            // Cálculo de hash para deduplicação
            $hash = hash('sha256', $content);

            // Armazenamento seguro
            $disk = Storage::disk(self::STORAGE_DISK);
            $disk->put($localPath, $content);

            // Extração de dimensões
            $dimensions = $this->extractDimensions($content);

            return [
                'path' => $localPath,
                'hash' => $hash,
                'mime' => $mime,
                'width' => $dimensions['width'] ?? 0,
                'height' => $dimensions['height'] ?? 0,
                'source_url' => $sourceUrl,
            ];
        } catch (Throwable $e) {
            Log::error("Erro ao baixar e validar imagem", [
                'url' => $sourceUrl,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Valida se a URL é segura e acessível.
     */
    private function isValidSourceUrl(string $url): bool
    {
        // Rejeita URLs locais, localhost, file://, data:, etc.
        if (preg_match('~^(file|data|javascript|about):~i', $url)) {
            return false;
        }

        // Whitelist básica de domínios confiáveis (adicionar conforme necessário)
        $trustedDomains = [
            'manaus.am.gov.br',
            'wikimedia.org',
            'commons.wikimedia.org',
            'unsplash.com',
            'images.unsplash.com',
            'source.unsplash.com',
            'pexels.com',
            'pixabay.com',
        ];

        $host = parse_url($url, PHP_URL_HOST);
        foreach ($trustedDomains as $domain) {
            if (str_ends_with((string) $host, $domain)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Valida MIME type da imagem.
     */
    private function isValidMime(string $mime): bool
    {
        $base = explode(';', strtolower($mime))[0] ?? '';
        return in_array($base, self::ALLOWED_MIMES, true);
    }

    /**
     * Extrai dimensões da imagem usando getimagesize em memória.
     */
    private function extractDimensions(string $content): array
    {
        try {
            // Cria arquivo temporário para getimagesize
            $tmp = tmpfile();
            fwrite($tmp, $content);
            fseek($tmp, 0);
            
            $info = getimagesize(stream_get_meta_data($tmp)['uri']);
            if ($info && isset($info[0], $info[1])) {
                return ['width' => $info[0], 'height' => $info[1]];
            }
        } catch (Throwable) {
            // Silenciosamente falha na extração
        }

        return ['width' => 0, 'height' => 0];
    }

    /**
     * Gera nome de arquivo seguro e único.
     * Exemplo: ecopontos/db-ponta-negra-a13fd92c.webp
     */
    public function generateSafePath(string $nome, string $bairro, string $hash = ''): string
    {
        // Slug do nome + bairro
        $slug = Str::slug($nome . '-' . $bairro, '-');
        
        // Se houver hash, usa os primeiros 8 caracteres
        $suffix = $hash ? substr($hash, 0, 8) : '';
        
        $filename = $suffix ? "{$slug}-{$suffix}.webp" : "{$slug}.webp";
        
        return self::STORAGE_PATH . '/' . substr($filename, 0, 255);
    }

    /**
     * Valida se hash já existe para evitar duplicatas.
     */
    public function hashExists(string $hash, ?int $excludeEcopontoId = null): bool
    {
        $query = \App\Models\Ecoponto::query()
            ->where('image_hash', $hash)
            ->where('image_verified', true);

        if ($excludeEcopontoId) {
            $query->where('id', '<>', $excludeEcopontoId);
        }

        return $query->exists();
    }

    /**
     * Recupera hash existente se a imagem já foi processada.
     */
    public function getExistingHashPath(string $hash): ?string
    {
        $ecoponto = \App\Models\Ecoponto::query()
            ->where('image_hash', $hash)
            ->where('image_verified', true)
            ->first(['imagem']);

        return $ecoponto?->imagem;
    }

    /**
     * Remove arquivo de imagem com segurança.
     */
    public function removeImage(string $path): bool
    {
        try {
            $disk = Storage::disk(self::STORAGE_DISK);
            if ($disk->exists($path)) {
                $disk->delete($path);
                return true;
            }
            return false;
        } catch (Throwable $e) {
            Log::warning("Erro ao remover imagem", [
                'path' => $path,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }
}
