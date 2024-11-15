<?php

use Yakovenko\LighthouseGraphqlMultiSchema\Services\GraphQLSchemaConfig;
use PHPUnit\Framework\TestCase;
use Illuminate\Http\Request;

class GraphQLSchemaConfigTest extends TestCase
{
    protected GraphQLSchemaConfig $graphQLSchemaConfig;

    protected function setUp(): void
    {
        // Mock the global request to control the request path for testing
        $this->mockRequestPath('/schema1-graphql');

        // Initialize GraphQLSchemaConfig
        $this->graphQLSchemaConfig = new GraphQLSchemaConfig();
    }

    /**
     * Mock the request path for testing.
     *
     * @param string $path The path to return when getPathInfo() is called.
     */
    protected function mockRequestPath( string $path ): void
    {
        $mockRequest = $this->createMock( Request::class );
        $mockRequest->method( 'getPathInfo' )->willReturn( $path );

        // Register the mock in Laravel's container
        $this->instance( Request::class, $mockRequest );
    }

    public function testGetSchemaKeyReturnsCorrectKey()
    {
        // Configure multi-schemas for testing
        config(['lighthouse-multi-schema.multi_schemas' => [
            'schema1' => [
                'route_uri' => '/schema1-graphql',
            ],
        ]]);

        // Verify that the correct schema key is returned
        $result = $this->graphQLSchemaConfig->getKey();
        $this->assertEquals( 'schema1', $result );
    }

    public function testGetSchemaKeyReturnsDefaultAppKey()
    {
        // Mock request path for default schema testing
        $this->mockRequestPath( '/default-graphql' );

        // Configure the default schema route
        config(['lighthouse.route.uri' => '/default-graphql']);

        // Verify that 'default' is returned as the schema key
        $result = $this->graphQLSchemaConfig->getKey();
        $this->assertEquals( 'default', $result );
    }

    public function testGetSchemaPathReturnsCorrectPath()
    {
        // Configure schema path for multi-schemas
        config(['lighthouse-multi-schema.multi_schemas' => [
            'schema1' => [
                'route_uri'   => '/schema1-graphql',
                'schema_path' => base_path('graphql/schema1.graphql'),
            ],
        ]]);

        // Verify that the correct schema path is returned
        $result = $this->graphQLSchemaConfig->getPath();
        $this->assertEquals( base_path( 'graphql/schema1.graphql' ), $result );
    }

    public function testGetSchemaPathReturnsDefaultPath()
    {
        // Configure default schema path
        config(['lighthouse.schema_path' => base_path('graphql/schema.graphql')]);

        // Verify that the default schema path is returned when no schema matches
        $result = $this->graphQLSchemaConfig->getPath();
        $this->assertEquals( base_path( 'graphql/schema.graphql' ), $result );
    }

    public function testGetSchemaCachePathReturnsCorrectPath()
    {
        // Configure schema cache path for multi-schemas
        config(['lighthouse-multi-schema.multi_schemas' => [
            'schema1' => [
                'route_uri'         => '/schema1-graphql',
                'schema_cache_path' => base_path( 'bootstrap/cache/schema1-schema.php' ),
            ],
        ]]);

        // Verify that the correct cache path is returned
        $result = $this->graphQLSchemaConfig->getCachePath();
        $this->assertEquals( base_path( 'bootstrap/cache/schema1-schema.php' ), $result );
    }

    public function testIsCacheEnabledReturnsTrueWhenEnabled()
    {
        // Configure schema cache enable for multi-schemas
        config(['lighthouse-multi-schema.multi_schemas' => [
            'schema1' => [
                'route_uri'           => '/schema1-graphql',
                'schema_cache_enable' => true,
            ],
        ]]);

        // Verify that caching is enabled
        $result = $this->graphQLSchemaConfig->isCacheEnabled();
        $this->assertTrue( $result );
    }

    public function testIsCacheEnabledReturnsFalseWhenDisabled()
    {
        // Configure schema cache disable for multi-schemas
        config(['lighthouse-multi-schema.multi_schemas' => [
            'schema1' => [
                'route_uri'           => '/schema1-graphql',
                'schema_cache_enable' => false,
            ],
        ]]);

        // Verify that caching is disabled
        $result = $this->graphQLSchemaConfig->isCacheEnabled();
        $this->assertFalse( $result );
    }
}
