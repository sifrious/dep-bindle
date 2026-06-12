<?php

declare(strict_types=1);

return [
    'has_form' => 'The page is interactive: a form on this page collects user input and submits it back to the server.',
    'has_table' => 'The page presents structured records that a reader can scan row-by-row.',
    'has_list' => 'The page presents a list of items that can be enumerated.',
    'has_modal' => 'A modal dialog is wired in for confirmations or detail views.',
    'has_livewire' => 'Interactions on this page are powered by Livewire and may update fragments of the page without a full reload.',
    'has_alpine' => 'Alpine.js handles small bits of inline interactivity on this page.',
    'has_inertia' => 'The page is rendered through Inertia, so navigation is handled client-side after the initial load.',
    'static' => 'The page is primarily static: no forms, no client-side framework wiring was detected.',
];
