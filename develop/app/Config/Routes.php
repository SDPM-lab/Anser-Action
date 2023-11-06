<?php

namespace Config;

// Create a new instance of our RouteCollection class.
$routes = Services::routes();

// Load the system's routing file first, so that the app and ENVIRONMENT
// can override as needed.
if (file_exists(SYSTEMPATH . 'Config/Routes.php'))
{
	require SYSTEMPATH . 'Config/Routes.php';
}

/**
 * --------------------------------------------------------------------
 * Router Setup
 * --------------------------------------------------------------------
 */
$routes->setDefaultNamespace('App\Controllers');
$routes->setDefaultController('Home');
$routes->setDefaultMethod('index');
$routes->setTranslateURIDashes(false);
$routes->set404Override();
$routes->setAutoRoute(true);

/*
 * --------------------------------------------------------------------
 * Route Definitions
 * --------------------------------------------------------------------
 */

// We get a performance increase by specifying the default
// route since we don't have to scan directories.
$routes->get('/', 'Home::index');

$routes->group(
    'api/v1',
    [
        'namespace' => 'App\Controllers\V1'
    ],
    function(\CodeIgniter\Router\RouteCollection $routes){
        //USER APIs
        $routes->resource("user",[
            'controller' => 'User',
            'only' => ['index','show','create'],
        ]);
        //ORDER APIs
        $routes->resource("order",[
            'controller' => 'Order',
            'only' => ['show', 'create', 'delete'],
        ]);
        //PAYMENT APIs
        $routes->resource("payment",[
            'controller' => 'Payment',
            'only' => ['show','create', 'delete'],
        ]);
        //Fail APIs
        $routes->get('fail','Fail::awayls429');
        $routes->get('fail/(:num)','Fail::awayls500/$1');

        //Rpc APIs
        $routes->get('doSingleAction','Rpc::doSingleAction');
        $routes->get('doConcurrentAction','Rpc::doConcurrentAction');
        $routes->get('doNativeAction','Rpc::doNativeAction');
        $routes->post('rpcServer','Rpc::rpcServer');
        $routes->post('errorRpcServer','Rpc::errorRpcServer');
    }
);


/*
 * --------------------------------------------------------------------
 * Additional Routing
 * --------------------------------------------------------------------
 *
 * There will often be times that you need additional routing and you
 * need it to be able to override any defaults in this file. Environment
 * based routes is one such time. require() additional route files here
 * to make that happen.
 *
 * You will have access to the $routes object within that file without
 * needing to reload it.
 */
if (file_exists(APPPATH . 'Config/' . ENVIRONMENT . '/Routes.php'))
{
	require APPPATH . 'Config/' . ENVIRONMENT . '/Routes.php';
}
