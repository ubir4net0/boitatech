<?php

namespace Database\Seeders;

use App\Models\Noticia;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class NoticiasSeeder extends Seeder
{
    public function run(): void
    {
        Noticia::query()->delete();

        $rows = [
            ['Evento do Ibama em Brasília discute práticas sobre manejo florestal sustentável', 'Encontro reúne instituições federais, estaduais e setor produtivo para discutir manejo, conservação e exploração de impacto reduzido.', 'noticias/ibama-manejo-florestal.webp', 'https://www.gov.br/ibama/pt-br/assuntos/noticias/2026/evento-do-ibama-em-brasilia-discute-praticas-sobre-manejo-florestal-sustentavel', 'IBAMA', 'fiscalizacao', '2026-05-15 10:00:00', true],
            ['Em tempos difíceis, que tal integridade ecológica?', 'Susan Lieberman defende cooperação internacional, conectividade de ecossistemas e integridade ecológica como resposta à crise climática.', 'noticias/infoamazonia-integridade-ecologica.webp', 'https://infoamazonia.org/2026/04/14/em-tempos-dificeis-que-tal-integridade-ecologica/', 'InfoAmazonia', 'clima', '2026-04-14 08:00:00', false],
            ['"Colonialismo disfarçado": indígenas criticam modelo de transição justa', 'Lideranças indígenas cobram direitos territoriais, participação direta e financiamento climático sem intermediários.', 'noticias/infoamazonia-transicao-justa.webp', 'https://infoamazonia.org/2026/04/29/colonialismo-disfarcado-indigenas-criticam-modelo-de-transicao-justa-que-nao-assegura-protecao-dos-territorios/', 'InfoAmazonia', 'povos-indigenas', '2026-04-29 13:30:00', true],
            ['Comunidades extrativistas denunciam violência e desmatamento em reserva', 'Relato de comunidades da Resex Jaci-Paraná mostra pressão fundiária, violência e avanço da destruição ambiental em Rondônia.', 'noticias/wwf-resex-jaci-parana.webp', 'https://www.wwf.org.br/?94650/resex-jaci-parana-comunidades-extrativistas-denunciam-violencia-e-desmatamento', 'WWF Brasil', 'desmatamento', '2026-05-01 09:00:00', false],
            ['ICMBio participa do maior fórum técnico-científico sobre áreas protegidas e conservadas do Brasil e da América Latina', 'Instituto reúne mais de 130 trabalhos e debate conservação, sociobiodiversidade e políticas para áreas protegidas.', 'noticias/icmbio-forum-areas-protegidas.png', 'https://www.gov.br/icmbio/pt-br/assuntos/noticias/ultimas-noticias/participacao-de-folego-icmbio-no-maior-forum-tecnico-cientifico-sobre-areas-protegidas-e-conservadas-do-brasil-e-da-america-latina', 'ICMBio', 'biodiversidade', '2026-05-15 09:00:00', false],
            ['UCBio: um novo e necessário palco para as unidades de conservação brasileiras', 'Conferência retoma o debate qualificado sobre UCs, biodiversidade, desafios jurídicos e a meta global de proteção.', 'noticias/ucbio-unidades-conservacao.webp', 'https://oeco.org.br/noticias/ucbio-um-novo-e-necessario-palco-para-as-unidades-de-conservacao-brasileiras/', 'O Eco', 'biodiversidade', '2026-05-15 08:30:00', false],
            ['MMA lança painel para monitorar agrotóxicos em recursos hídricos', 'Plataforma pública amplia transparência sobre contaminantes, biodiversidade aquática e qualidade da água no país.', 'noticias/mma-agrotoxicos-recursos-hidricos.webp', 'https://oeco.org.br/salada-verde/mma-lanca-painel-para-monitorar-agrotoxicos-em-recursos-hidricos/', 'O Eco', 'monitoramento', '2026-05-14 08:00:00', true],
            ['Leis fundiárias na Amazônia facilitam grilagem de terras públicas, aponta estudo', 'Imazon identifica brechas legais que favorecem ocupações irregulares, grilagem e consolidação de passivos ambientais.', 'noticias/oeco-grilagem-terras-publicas.webp', 'https://oeco.org.br/noticias/leis-fundiarias-na-amazonia-facilitam-grilagem-de-terras-publicas-aponta-estudo/', 'O Eco', 'amazonia', '2026-05-14 08:00:00', true],
        ];

        foreach ($rows as [$titulo, $resumo, $imagemPath, $link, $fonte, $categoria, $publicado, $destaque]) {
            $slug = Str::slug($titulo);
            $imagemUrl = '/storage/' . $imagemPath;

            Noticia::query()->create([
                'titulo' => $titulo,
                'resumo' => $resumo,
                'imagem_path' => $imagemPath,
                'imagem_url' => $imagemUrl,
                'link_original' => $link,
                'fonte' => $fonte,
                'categoria' => $categoria,
                'publicado_em' => Carbon::parse($publicado),
                'slug' => $slug,
                'hash' => hash('sha256', $link.'|'.$titulo),
                'is_destaque' => (bool) $destaque,
            ]);
        }
    }
}
