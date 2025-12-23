const Encore = require('@symfony/webpack-encore');
const path = require('path');

if (!Encore.isRuntimeEnvironmentConfigured()) {
  Encore.configureRuntimeEnvironment(process.env.NODE_ENV || 'dev');
}

Encore
  .setOutputPath('dist/')
  .setPublicPath('/templates-assets/frontOffice/moderna/dist')
  .setManifestKeyPrefix('moderna')

  // Main entry point
  .addEntry('app', './assets/js/app.js')

  // Enable Stimulus
  .enableStimulusBridge('./assets/controllers.json')

  // Features
  .splitEntryChunks()
  .enableSingleRuntimeChunk()
  .cleanupOutputBeforeBuild()
  .enableSourceMaps(!Encore.isProduction())
  .enableVersioning(Encore.isProduction())

  // PostCSS for Tailwind
  .enablePostCssLoader()

  // Copy images
  .copyFiles({
    from: './assets/images',
    to: 'images/[path][name].[ext]',
  })

  // Configure Babel
  .configureBabelPresetEnv((config) => {
    config.useBuiltIns = 'usage';
    config.corejs = '3.23';
  })
;

const config = Encore.getWebpackConfig();

// Resolve aliases
config.resolve.alias = {
  ...config.resolve.alias,
  '@': path.resolve(__dirname, 'assets'),
  '@components': path.resolve(__dirname, 'components'),
};

module.exports = config;
