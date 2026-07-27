<?php

use craft\elements\Entry;

return [

    'siteName' => '{{ seo.siteName }}',
    'sitemapName' => 'sitemap',
    'sitemapEnabled' => true,
    'sitemapLimit' => 50000,
    'sitemapConfig' => [
        'elements' => [
            // main chamber homepage
            'home' => ['changefreq' => 'daily', 'priority' => 1],
            // listing pages
            'blogListing' => ['changefreq' => 'weekly', 'priority' => .8],
            // singles
            'howTheyVoted' => ['changefreq' => 'monthly', 'priority' => .5],
            // channels
            'pages' => ['changefreq' => 'daily', 'priority' => .7],
            'blog' => ['changefreq' => 'daily', 'priority' => .7],
        ],
    ],

    'defaultMeta' => [
        'title' => ['seo.siteName'],
        'description' => ['seo.metaDescription'],
        'image' => ['seo.image'],
    ],

    'defaultProfile' => 'standard',

    'fieldProfiles' => [
        'standard' => [
            'title' => ['title'],
            'description' => ['summary', 'articleBody', 'seo.metaDescription'],
            'image' => ['featuredImage', 'seo.image'],
        ],
    ],

    'additionalMeta' => [
        'og:type' => 'website',
        'og:site_name' => '{{ seo.siteName }}',
        'twitter:card' => 'summary_large_image',
    ],

    // Same as SEOMate's default, plus article:* rendered as Open Graph property tags
    'tagTemplateMap' => [
        'default' => '<meta name="{{ key }}" content="{{ value }}">',
        'title' => '<title>{{ value }}</title>',
        '/^og:/,/^fb:/,/^article:/' => '<meta property="{{ key }}" content="{{ value }}">',
    ],

];
