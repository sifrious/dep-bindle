<?php

declare(strict_types=1);

return [
    'opening' => [
        'fullwidth_hero' => 'The page opens with a full-width hero region containing {hero_summary}.',
        'simple_header' => 'The page begins with a top navigation bar followed by a {h1_phrase}.',
        'plain' => 'The page begins with {h1_phrase}.',
        'spa_shell' => 'The page is mounted as a single-page application root and begins with {h1_phrase}.',
    ],
    'middle' => [
        'form_heavy' => 'The body is dominated by a {field_count}-field form for {form_purpose}.',
        'table_heavy' => 'The body is centered on a data table presenting {row_count} rows.',
        'card_grid' => 'The body presents a grid of {card_count} card-like blocks summarizing related items.',
        'narrative' => 'The body unfolds as a narrative text region with {paragraph_count} paragraphs of supporting content.',
        'mixed' => 'The body mixes a primary content region with secondary widgets to the side.',
    ],
    'closing' => [
        'footer_links' => 'A footer with grouped links closes the layout.',
        'cta_strip' => 'A call-to-action strip near the bottom invites the visitor to {cta_phrase}.',
        'minimal' => 'The page ends without an explicit footer.',
    ],
];
