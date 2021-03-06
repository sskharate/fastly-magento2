<?php

namespace Fastly\Cdn\Helper;

use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use Magento\Framework\App\DeploymentConfig\Reader;
use Magento\Framework\Exception\LocalizedException;

class Vcl extends AbstractHelper
{
    /**
     * @var Reader
     */
    private $configReader;

    /**
     * Templetize constructor.
     * @param Context $context
     * @param Reader $configReader
     */
    public function __construct(
        Context $context,
        Reader $configReader
    ) {
        $this->configReader = $configReader;

        parent::__construct($context);
    }

    /**
     * Fetch current version
     * @param array $versions
     * @return mixed
     * @throws LocalizedException
     */
    public function getCurrentVersion(array $versions)
    {
        if (!empty($versions)) {
            foreach ($versions as $version) {
                if ($version->active) {
                    return $activeVersion = $version->number;
                }
            }
        }

        throw new LocalizedException(__('Error fetching current version.'));
    }

    /**
     * Fetch next version
     * @param array $versions
     * @return int
     * @throws LocalizedException
     */
    public function getNextVersion(array $versions)
    {
        if (isset(end($versions)->number)) {
            return (int) end($versions)->number + 1;
        }

        throw new LocalizedException(__('Error fetching next version.'));
    }

    /**
     * Check if active versions (local and remote) are in sync
     * @param $versions
     * @param $activeVersion
     * @return bool
     * @throws LocalizedException
     */
    public function checkCurrentVersionActive($versions, $activeVersion)
    {
        $current = $this->getCurrentVersion($versions);

        if ($current != $activeVersion) {
            throw new LocalizedException(__('Active versions mismatch.'));
        }

        return true;
    }

    /**
     * @return mixed
     * @throws \Exception
     * @throws \Magento\Framework\Exception\FileSystemException
     */
    public function getAdminFrontName()
    {
        $config = $this->configReader->load();
        $adminFrontName = $config['backend']['frontName'];

        return $adminFrontName;
    }
}
