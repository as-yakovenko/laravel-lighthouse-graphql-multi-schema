<?php

namespace Yakovenko\LighthouseGraphqlMultiSchema\Services;

use Illuminate\Container\Container;
use Nuwave\Lighthouse\Http\GraphQLController;

class GraphQLRouteRegister
{
    /**
     * Registers GraphQL routes based on the multi-schema configuration.
     *
     * This method iterates over the defined multi-schemas in the configuration,
     * setting up routes with the specified URI, route name, and optional middleware.
     *
     * @return void
     */
    public function registerGraphQLRoutes(): void
    {
        // Retrieve the Laravel container instance
        $container    = Container::getInstance();

        // Retrieve the router instance from the container
        $router       = $container->make('router');

        // Get multi-schema configurations from config
        $multiSchemas = config('lighthouse-multi-schema.multi_schemas', []);

        // Iterate through each schema configuration in multiSchemas
        foreach ( $multiSchemas as $schemaConfig ) {

            // Define the action for the route, setting route name and controller
            $action = [
                'as'   => $schemaConfig['route_name'],
                'uses' => GraphQLController::class,
            ];

            // Add middleware if specified in the schema configuration
            if ( isset( $schemaConfig['middleware'] ) ) {
                $action['middleware'] = $schemaConfig['middleware'];
            }

            // Register the route with both GET and POST methods
            $router->addRoute(
                ['GET', 'POST'],
                $schemaConfig['route_uri'],
                $action
            );
            
        }
    }
}
