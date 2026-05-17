<?php

return [
    'categories' => [
        'queimadas' => [
            'label' => 'Queimadas',
            'icon' => '🔥',
            'color' => '#FF5A36',
            'description' => 'incêndios florestais, fumaça intensa e queimadas ilegais',
        ],
        'desmatamento' => [
            'label' => 'Desmatamento',
            'icon' => '🌳',
            'color' => '#00D084',
            'description' => 'corte ilegal, remoção de vegetação e abertura irregular de área',
        ],
        'poluicao-ambiental' => [
            'label' => 'Poluição Ambiental',
            'icon' => '🛢️',
            'color' => '#0EA5E9',
            'description' => 'rios contaminados, resíduos tóxicos e vazamentos',
        ],
        'crime-contra-fauna' => [
            'label' => 'Crime contra Fauna',
            'icon' => '🐾',
            'color' => '#F59E0B',
            'description' => 'caça ilegal, tráfico de animais e maus-tratos',
        ],
        'descarte-irregular' => [
            'label' => 'Descarte Irregular',
            'icon' => '🚯',
            'color' => '#A855F7',
            'description' => 'lixo em áreas protegidas e descarte clandestino',
        ],
        'garimpo-ilegal' => [
            'label' => 'Garimpo Ilegal',
            'icon' => '⛏️',
            'color' => '#EAB308',
            'description' => 'mineração clandestina e degradação de rios',
        ],
        'invasao-area-protegida' => [
            'label' => 'Invasão de Área Protegida',
            'icon' => '🏕️',
            'color' => '#EF4444',
            'description' => 'ocupação irregular, construções ilegais e invasão territorial',
        ],
        'contaminacao-hidrica' => [
            'label' => 'Contaminação Hídrica',
            'icon' => '🌊',
            'color' => '#06B6D4',
            'description' => 'contaminação de rios, óleo e mortandade de peixes',
        ],
    ],

    'statuses' => [
        'em_analise' => ['label' => 'Em análise', 'color' => '#F59E0B'],
        'em_verificacao' => ['label' => 'Em verificação', 'color' => '#0EA5E9'],
        'confirmada' => ['label' => 'Confirmada', 'color' => '#22C55E'],
        'descartada' => ['label' => 'Descartada', 'color' => '#EF4444'],
    ],

    'map' => [
        'brazil_bounds' => [
            'west' => -74.5,
            'south' => -34.5,
            'east' => -28.0,
            'north' => 6.2,
        ],
        'default_center' => [-53.0, -12.0],
        'default_height' => 4_900_000,
    ],
];