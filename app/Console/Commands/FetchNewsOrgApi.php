<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Http\Controllers\NewsController;

class FetchNewsOrgApi extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'fetch:newsorg';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Fetch articles from NewsApi.ORG';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $result = app(NewsController::class)->fetchApi('newsorg');
        return Command::SUCCESS;
    }
}
