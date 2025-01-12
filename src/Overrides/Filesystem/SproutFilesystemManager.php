<?php
declare(strict_types=1);

namespace Sprout\Overrides\Filesystem;

use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Filesystem\FilesystemManager;
use InvalidArgumentException;

final class SproutFilesystemManager extends FilesystemManager
{
    public function __construct($app, ?FilesystemManager $original = null)
    {
        parent::__construct($app);

        if ($original) {
            $this->syncOriginal($original);
        }
    }

    /**
     * Sync the original manager in case things have been registered
     *
     * @param \Illuminate\Filesystem\FilesystemManager $original
     *
     * @return void
     */
    private function syncOriginal(FilesystemManager $original): void
    {
        $this->disks          = array_merge($original->disks, $this->disks);
        $this->customCreators = array_merge($original->customCreators, $this->customCreators);
    }

    /**
     * Resolve the given disk.
     *
     * @param string                    $name
     * @param array<string, mixed>|null $config
     *
     * @return \Illuminate\Contracts\Filesystem\Filesystem
     *
     * @throws \InvalidArgumentException
     */
    protected function resolve($name, $config = null): Filesystem
    {
        $config ??= $this->getConfig($name);

        if (empty($config['driver'])) {
            throw new InvalidArgumentException("Disk [{$name}] does not have a configured driver.");
        }

        $config['name'] = $name;

        $driver = $config['driver'];

        if (isset($this->customCreators[$driver])) {
            return $this->callCustomCreator($config);
        }

        $driverMethod = 'create' . ucfirst($driver) . 'Driver';

        if (! method_exists($this, $driverMethod)) {
            throw new InvalidArgumentException("Driver [{$driver}] is not supported.");
        }

        return $this->{$driverMethod}($config, $name);
    }
}
