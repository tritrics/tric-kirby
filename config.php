<?php

use Kirby\Exception\Exception;
use Tritrics\AflevereApi\v1\Controllers\ApiController;
use Tritrics\AflevereApi\v1\Services\FileService;
use Tritrics\AflevereApi\v1\Services\LanguagesService;
use Tritrics\AflevereApi\v1\Services\ApiService;

/**
 * Plugin registration
 */
kirby()::plugin(ApiService::$pluginName, [
  'options' => [
    'enabled' => [
      'info' => false,
      'page' => false,
      'pages' => false,
      'action' => false
    ],
    'slug' => 'public-api',
    'field-name-separator' => '_',
  ],
  'hooks' => [
    'page.create:before' => function ($page, array $input) {
      if (ApiService::isProtectedSlug($input['slug'])) {
        throw new Exception('Slug not allowed');
      }
    },
    'page.changeSlug:before' => function ($page, string $slug, ?string $languageCode = null) {
      if (ApiService::isProtectedSlug($slug)) {
        throw new Exception('Slug not allowed');
      }
    },
    'route:before' => function ($route, $path, $method) {
      $attributes = $route->attributes();
      if (
        $method === 'GET' &&
        isset($attributes['env']) &&
        $attributes['env'] === 'media' &&
        is_string($path) &&
        !is_file(kirby()->root('index') . '/' . $path)) {
          FileService::getImage($path, $route->arguments(), $route->pattern());
      }
      return;
    }
  ],
  'routes' => function ($kirby) {
    $slug = ApiService::getApiSlug();
    if (!$slug) {
      return [];
    }
    $multilang = LanguagesService::isMultilang();
    $routes = array();

    // language-based routes, only relevant if any language
    // exists in site/languages/
    if ($multilang) {

      // default kirby route must be overwritten to prevent kirby
      // from redirecting to default language. This is done by
      // the frontend.
      $routes[] = [
        'pattern' => '',
        'method'  => 'ALL',
        'env'     => 'site',
        'action'  => function () use ($kirby) {
          return $kirby->defaultLanguage()->router()->call();
        }
      ];
    }

    // expose
    if (ApiService::isEnabledInfo()) {
      $routes[] = [
        'pattern' => $slug . '/info',
        'method' => 'GET',
        'action' => function () {
          $controller = new ApiController();
          return $controller->info();
        }
      ];
    }

    // a language
    if (ApiService::isEnabledLanguage()) {
      $routes[] = [
        'pattern' => $slug . '/language/(:any)',
        'method' => 'GET',
        'action' => function ($resource = '') use ($multilang) {
          $controller = new ApiController();
          return $controller->language($resource);
        }
      ];
    }

    // a node
    if (ApiService::isEnabledPage()) {
      $routes[] = [
        'pattern' => $slug . '/page/(:all?)',
        'method' => 'GET|POST|OPTIONS',
        'action' => function ($resource = '') use ($multilang) {
          list($lang, $path) = ApiService::parsePath($resource, $multilang);
          $controller = new ApiController();
          return $controller->page($lang, $path);
        }
      ];
    }

    // children of a node
    if (ApiService::isEnabledPages()) {
      $routes[] = [
        'pattern' => $slug . '/pages/(:all?)',
        'method' => 'GET|POST|OPTIONS',
        'action' => function ($resource = '') use ($multilang) {
          list($lang, $path) = ApiService::parsePath($resource, $multilang);
          $controller = new ApiController();
          return $controller->pages($lang, $path);
        }
      ];
    }

    // action (post-data) handling
    if (ApiService::isEnabledAction()) {
      $routes[] = [
        'pattern' => $slug . '/action/(:all?)',
        'method' => 'GET|POST|OPTIONS',
        'action' => function ($resource = '') use ($multilang) {
          list($lang, $action) = ApiService::parsePath($resource, $multilang);
          $controller = new ApiController();
          return $controller->action($lang, $action);
        }
      ];
    }
    return $routes;
  }
]);
