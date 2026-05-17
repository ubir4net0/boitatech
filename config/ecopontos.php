<?php

return [
    'city' => [
        'name' => 'Manaus',
        'uf' => 'AM',
        'center' => [-3.1190, -60.0217],
        'default_zoom' => 12,
        'bounds' => [
            'south' => -3.2800,
            'west' => -60.1800,
            'north' => -2.9800,
            'east' => -59.8600,
        ],
    ],

    'categories' => [
        'coleta-seletiva' => [
            'label' => 'Coleta Seletiva',
            'icon' => '♻️',
            'color' => '#3DFF9A',
        ],
        'descarte-eletronico' => [
            'label' => 'Descarte Eletrônico',
            'icon' => '🔋',
            'color' => '#4CC9F0',
        ],
        'pilhas-baterias' => [
            'label' => 'Pilhas e Baterias',
            'icon' => '🪫',
            'color' => '#FB7185',
        ],
        'oleo-cozinha' => [
            'label' => 'Óleo de Cozinha',
            'icon' => '🛢️',
            'color' => '#F59E0B',
        ],
        'papel-papelao' => [
            'label' => 'Papel e Papelão',
            'icon' => '📄',
            'color' => '#A3E635',
        ],
        'plastico' => [
            'label' => 'Plástico',
            'icon' => '🧴',
            'color' => '#22D3EE',
        ],
        'vidro' => [
            'label' => 'Vidro',
            'icon' => '🍾',
            'color' => '#8B5CF6',
        ],
        'reciclaveis-gerais' => [
            'label' => 'Recicláveis Gerais',
            'icon' => '🧺',
            'color' => '#F472B6',
        ],
    ],

    'material_aliases' => [
        'pilha' => 'pilhas',
        'pilhas' => 'pilhas',
        'bateria' => 'baterias',
        'baterias' => 'baterias',
        'eletronico' => 'eletronicos',
        'eletrônico' => 'eletronicos',
        'eletronicos' => 'eletronicos',
        'eletrônicos' => 'eletronicos',
        'plastico' => 'plastico',
        'plástico' => 'plastico',
        'papel' => 'papel',
        'papelao' => 'papelao',
        'papelão' => 'papelao',
        'vidro' => 'vidro',
        'oleo' => 'oleo',
        'óleo' => 'oleo',
        'metal' => 'metal',
    ],
];
