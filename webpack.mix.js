const mix = require('laravel-mix');

/*
 |--------------------------------------------------------------------------
 | Mix Asset Management
 |--------------------------------------------------------------------------
 |
 | Mix provides a clean, fluent API for defining some Webpack build steps
 | for your Laravel application. By default, we are compiling the Sass
 | file for the application as well as bundling up all the JS files.
 |
 */

mix.react('resources/js/app.js', 'public/js')
    .sass('resources/sass/app.scss', 'public/css');
if (mix.inProduction()) {
   mix.version();
}

/**
 * we are compiling the Moment files for the application as well as bundling up all the JS files.
 */
mix.scripts([
    'public/asset/vendors/moment/moment.min.js',
    'public/asset/vendors/moment/moment_data.min.js',
    'public/asset/vendors/moment/moment_timezones.min.js',
], 'public/js/moment_min.js');