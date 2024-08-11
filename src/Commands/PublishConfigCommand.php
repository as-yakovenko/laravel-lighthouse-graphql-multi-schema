<?php

namespace Yakovenko\LighthouseGraphqlMultiSchema\Commands;

use Illuminate\Console\Command;

class PublishConfigCommand extends Command
{
    // The signature of the command, defining how it can be called from the console.
    protected $signature    = 'lighthouse-multi-schema:publish-config';

     // A brief description of what the command does, shown when listing commands.
    protected $description  = 'Publish the lighthouse-multi-schema configuration file';

    /**
     * Execute the console command.
     *
     * This method is called when the command is executed.
     * It publishes the configuration file for the Lighthouse multi-schema package.
     *
     * @return void
     */
    public function handle(): void
    {
        $this->call('vendor:publish', [
            '--provider' => "Yakovenko\LighthouseGraphqlMultiSchema\LighthouseMultiSchemaServiceProvider",
            '--tag' => 'config'
        ]);

        $this->info( 'Configuration file published successfully.' );
    }
}
