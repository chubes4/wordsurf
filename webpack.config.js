const defaultConfig = require('@wordpress/scripts/config/webpack.config');

module.exports = {
    ...defaultConfig,
    entry: {
        'editor': './src/js/WordsurfPlugin.js',
        'admin': './src/js/admin/index.js',
    },
    output: {
        path: __dirname + '/assets/js',
        filename: '[name].js',
    },
}; 