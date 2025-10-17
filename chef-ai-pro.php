<?php
/**
 * Plugin Name:       Chef-AI Pro: Gerador de Receitas Automático Otimizado
 * Plugin URI:        https://example.com/chef-ai-pro
 * Description:       Gera posts de receitas otimizados para SEO com IA, focando em performance, segurança e EEAT.
 * Version:           1.0.0
 * Author:            Seu Nome
 * Author URI:        https://example.com
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       chef-ai-pro
 * Domain Path:       /languages
 */

// Se este arquivo for chamado diretamente, aborte.
if (!defined('ABSPATH')) {
    exit;
}

// Constantes do Plugin
define('CHEF_AI_PRO_VERSION', '1.0.0');
define('CHEF_AI_PRO_PATH', plugin_dir_path(__FILE__));
define('CHEF_AI_PRO_URL', plugin_dir_url(__FILE__));
define('CHEF_AI_PRO_BASENAME', plugin_basename(__FILE__));

// Autoloader PSR-4 para as classes
spl_autoload_register(function ($class) {
    // Apenas carrega classes do nosso namespace
    $prefix = 'ChefAIPro\\';
    $base_dir = __DIR__ . '/includes/';

    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }

    $relative_class = substr($class, $len);
    $file = $base_dir . str_replace('\\', '/', $relative_class) . '.php';

    if (file_exists($file)) {
        require $file;
    }
});

// Inicializa o plugin
function chef_ai_pro_init() {
    new \ChefAIPro\Assets();
    new \ChefAIPro\Admin();
    new \ChefAIPro\Generator();
}
add_action('plugins_loaded', 'chef_ai_pro_init');
