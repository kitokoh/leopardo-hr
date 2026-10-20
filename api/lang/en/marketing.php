<?php

// Marketing & Communication — tranche 1 (#7751)
return [
    'ai' => [
        'suggestion_unavailable' => 'The AI suggestion is unavailable right now. Try again later.',
    ],
    'social' => [
        'default_platform' => 'social network',
        'interactions_published_only' => 'Interactions are only available for a published post (aggregator reference required).',
    ],
    // Marketing OS — Super Admin marketing command centre (#8528)
    'os' => [
        'task_title' => 'Marketing production: :title',
        'task_title_with_campaign' => 'Marketing production: :title [:campaign]',
        'task_objective' => 'Objective: :value',
        'task_format' => 'Format: :value',
        'task_locale' => 'Language: :value',
        'task_campaign' => 'Campaign: :value',
        'task_product' => 'Product: :value',
        'task_sector' => 'Vertical: :value',
        'task_audience' => 'Target audience: :value',
        'task_key_message' => 'Key message: :value',
        'task_publish_due' => 'Desired publication date: :date.',
        'objective_unspecified' => 'not specified',
        'format_unspecified' => 'Not specified',
        'mode_direct_explanation' => 'A publishing connector is configured: the publication can be executed through the API.',
        'mode_assisted_explanation' => 'No connector configured: the publication will be assisted (preparation + opening the platform, manual confirmation).',
        'idea_not_found' => 'Idea not found for this company.',
        'campaign_not_found' => 'Campaign not found for this company.',
        'asset_not_found' => 'Asset not found for this company.',
        'file_required' => 'A file is required for a resource of type “:kind”.',
        // #8675 — downloading a media library resource.
        'asset_has_no_file' => 'This resource is text content (script, prompt, caption): it has no file to download.',
        'asset_file_missing' => 'The file for this resource cannot be found in storage. The record still exists; the binary may have been purged or the restore is incomplete.',
        // #8531 — SEO/SEA/AEO/GEO visibility: no visibility is promised.
        'visibility_no_guarantee' => 'No visibility is guaranteed in search engines or AI assistants: indicators are measured or explicitly identified as estimates.',
    ],
];
