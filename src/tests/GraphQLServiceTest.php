<?php

use Yakovenko\LighthouseGraphqlMultiSchema\Services\GraphQLService;
use PHPUnit\Framework\TestCase;

class GraphQLServiceTest extends TestCase
{
    protected GraphQLService $graphQLService;

    protected function setUp(): void
    {
        // Mocking the config function to return the necessary configurations
        $this->graphQLService = new GraphQLService();
    }

    public function testGetSchemaPathReturnsCorrectPath()
    {
        $mockConfig = [
            'example' => [
                'route_uri'   => '/example-graphql',
                'schema_path' => base_path( "graphql/example.graphql" ),
            ],
        ];
        
        config( ['lighthouse-multi-schema.multi_schemas' => $mockConfig] );

        $result = $this->graphQLService->getSchemaPath( '/example-graphql' );
        $this->assertEquals( base_path( "graphql/example.graphql" ), $result);
    }

    public function testGetSchemaPathReturnsDefaultPath()
    {
        config( ['lighthouse.schema_path' => 'graphql/schema.graphql'] );

        $result = $this->graphQLService->getSchemaPath( '/unknown-uri' );
        $this->assertEquals( base_path( "graphql/example.graphql" ), $result );
    }

    public function testGetSchemaCacheReturnsCorrectPath()
    {
        $mockConfig = [
            'example' => [
                'route_uri'         => '/example-graphql',
                'schema_cache_path' => base_path( "bootstrap/cache/example-schema.php" ),
            ],
        ];

        config( ['lighthouse-multi-schema.multi_schemas' => $mockConfig] );

        $result = $this->graphQLService->getSchemaCache('/example-graphql' );
        $this->assertEquals( base_path( "bootstrap/cache/example-schema.php" ), $result );
    }

    public function testIsCacheEnabledReturnsTrueWhenEnabled()
    {
        $mockConfig = [
            'example' => [
                'route_uri'           => '/example-graphql',
                'schema_cache_enable' => true,
            ],
        ];

        config( ['lighthouse-multi-schema.multi_schemas' => $mockConfig] );

        $result = $this->graphQLService->isCacheEnabled( '/example-graphql' );
        $this->assertTrue( $result );
    }

    public function testIsCacheEnabledReturnsFalseWhenDisabled()
    {
        $mockConfig = [
            'example' => [
                'route_uri'           => '/example-graphql',
                'schema_cache_enable' => false,
            ],
        ];

        config( ['lighthouse-multi-schema.multi_schemas' => $mockConfig] );

        $result = $this->graphQLService->isCacheEnabled( '/example-graphql' );
        $this->assertFalse( $result );
    }
}
