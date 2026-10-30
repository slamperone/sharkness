<?php

class Dispatcher extends DispatcherCore
{
    public function __construct()
    {
        // Definición de product_rule 100% limpia sin IDs
        $this->default_routes['product_rule'] = [
            'controller' => 'product',
            'rule' => '{categories:/}{rewrite}.html',
            'keywords' => [
                'id' => ['regexp' => '[0-9]+'],
                'id_product_attribute' => ['regexp' => '[0-9]*+'],
                'rewrite' => ['regexp' => self::REWRITE_PATTERN, 'param' => 'rewrite'],
                'ean13' => ['regexp' => '[0-9\pL]*'],
                'category' => ['regexp' => '[_a-zA-Z0-9-\pL]*'],
                'categories' => ['regexp' => '[/_a-zA-Z0-9-\pL]*'],
                'reference' => ['regexp' => '[_a-zA-Z0-9-\pL]*'],
                'meta_title' => ['regexp' => '[_a-zA-Z0-9-\pL]*'],
                'manufacturer' => ['regexp' => '[_a-zA-Z0-9-\pL]*'],
                'supplier' => ['regexp' => '[_a-zA-Z0-9-\pL]*'],
                'price' => ['regexp' => '[0-9\.,]*'],
                'tags' => ['regexp' => '[a-zA-Z0-9-\pL]*'],
            ],
        ];

        // Definición de category_rule 100% limpia sin IDs
        $this->default_routes['category_rule'] = [
            'controller' => 'category',
            'rule' => '{categories:/}{rewrite}',
            'keywords' => [
                'id' => ['regexp' => '[0-9]+'],
                'rewrite' => ['regexp' => self::REWRITE_PATTERN, 'param' => 'rewrite'],
                'meta_title' => ['regexp' => '[_a-zA-Z0-9-\pL]*'],
                'categories' => ['regexp' => '[/_a-zA-Z0-9-\pL]*'],
            ],
        ];

        parent::__construct();
    }

    public function createUrl(
        $routeName,
        $id_lang = null,
        array $params = [],
        $force_routes = false,
        $anchor = '',
        $id_shop = null
    ) {
        // En nuestras rutas limpias no queremos que id, category o id_product_attribute salgan como query params ?id=...
        if ($routeName === 'product_rule' || $routeName === 'category_rule') {
            unset($params['id'], $params['category'], $params['id_product_attribute']);
        }

        return parent::createUrl($routeName, $id_lang, $params, $force_routes, $anchor, $id_shop);
    }

    public function getController($id_shop = null)
    {
        // Si estamos en la raíz '/', debe ser el index
        $requestUri = isset($this->request_uri) ? $this->request_uri : (isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '');
        $path = trim(parse_url($requestUri, PHP_URL_PATH), '/');
        if (empty($path)) {
            $this->controller = 'index';
            return 'index';
        }

        $controller = parent::getController($id_shop);

        // Si se resolvió como producto y no tenemos id_product, resolverlo por link_rewrite
        if ($controller === 'product' && empty($_GET['id_product']) && !empty($_GET['rewrite'])) {
            $idLang = (int)Context::getContext()->language->id;
            $rewrite = pSQL($_GET['rewrite']);
            $idProduct = (int)Db::getInstance()->getValue(
                'SELECT id_product FROM ' . _DB_PREFIX_ . 'product_lang WHERE link_rewrite = \'' . $rewrite . '\' AND id_lang = ' . $idLang
            );
            if (!$idProduct) {
                $idProduct = (int)Db::getInstance()->getValue(
                    'SELECT id_product FROM ' . _DB_PREFIX_ . 'product_lang WHERE link_rewrite = \'' . $rewrite . '\''
                );
            }
            if ($idProduct) {
                $_GET['id_product'] = $idProduct;
            } else {
                $controller = $this->controller_not_found;
            }
        }

        // Si se resolvió como category y no tenemos id_category, resolverlo por link_rewrite
        if ($controller === 'category' && empty($_GET['id_category'])) {
            if (empty($_GET['rewrite'])) {
                // Si rewrite está vacío, no puede ser una categoría
                return 'index';
            }
            $idLang = (int)Context::getContext()->language->id;
            $rewrite = pSQL($_GET['rewrite']);
            $idCategory = (int)Db::getInstance()->getValue(
                'SELECT id_category FROM ' . _DB_PREFIX_ . 'category_lang WHERE link_rewrite = \'' . $rewrite . '\' AND id_lang = ' . $idLang
            );
            if (!$idCategory) {
                $idCategory = (int)Db::getInstance()->getValue(
                    'SELECT id_category FROM ' . _DB_PREFIX_ . 'category_lang WHERE link_rewrite = \'' . $rewrite . '\''
                );
            }
            if ($idCategory) {
                $_GET['id_category'] = $idCategory;
            } else {
                $controller = $this->controller_not_found;
            }
        }

        return $controller;
    }
}
