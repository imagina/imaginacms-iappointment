<?php
use Illuminate\Routing\Router;

$router->group(['prefix' => '/iappointment/v1'/*,'middleware' => ['auth:api']*/], function (Router $router) {
//======  CATEGORIES
    require('ApiRoutes/categoryRoutes.php');

    require('ApiRoutes/appointmentRoutes.php');
});
