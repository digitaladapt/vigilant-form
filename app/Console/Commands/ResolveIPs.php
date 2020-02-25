<?php

namespace App\Console\Commands;

use App\IPAddress;
use Illuminate\Console\Command;

class ResolveIPs extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'resolve:ips';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Resolve any outstanding IPs.';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $ipAddresses = IPAddress::whereNull('hostname')->get();
        foreach ($ipAddresses as $ipAddress) {
            $ipAddress->resolve();
            $ipAddress->save();
        }
    }
}
