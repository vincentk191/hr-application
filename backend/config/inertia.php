<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Server Side Rendering
    |--------------------------------------------------------------------------
    |
    | These options configures if and how Inertia uses Server Side Rendering
    | to pre-render each initial request made to your application's pages
    | so that server rendered HTML is delivered for the user's browser.
    |
    | See: https://inertiajs.com/server-side-rendering
    |
    */

    'ssr' => [
        // Env-driven so the Docker dev stack can switch it off: in dev, SSR means every page
        // render makes a second round trip into the Vite container, which roughly doubles the
        // work per request and buys nothing locally. Production keeps it on.
        'enabled' => env('INERTIA_SSR_ENABLED', true),
        'url' => 'http://127.0.0.1:13714',
        // 'bundle' => base_path('bootstrap/ssr/ssr.mjs'),

        // While Vite runs hot, Inertia reads the SSR endpoint out of public/hot. That file has
        // to contain a URL the *browser* can reach (localhost), which is not a URL the app can
        // reach when the two run in separate containers. This override lets the server-side
        // call use the compose service name while the browser keeps localhost.
        // Unset outside Docker, so the hot file is used as normal.
        'hot_url' => env('INERTIA_SSR_HOT_URL'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Pages
    |--------------------------------------------------------------------------
    |
    | These options configure how Inertia discovers page components on the
    | filesystem. The paths and extensions are used to locate components
    | when rendering responses and during testing assertions.
    |
    */

    'pages' => [

        'paths' => [
            // The pages belong to the frontend project, which sits beside this one.
            env('INERTIA_PAGES_PATH', base_path('../frontend/src/pages')),
        ],

        'extensions' => [
            'js',
            'jsx',
            'svelte',
            'ts',
            'tsx',
            'vue',
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Testing
    |--------------------------------------------------------------------------
    |
    | The values described here are used to locate Inertia components on the
    | filesystem. For instance, when using `assertInertia`, the assertion
    | attempts to locate the component as a file relative to the paths.
    |
    */

    'testing' => [

        'ensure_pages_exist' => true,

    ],

];
