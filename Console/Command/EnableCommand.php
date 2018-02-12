<?php
/**
 * Fastly CDN for Magento
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Fastly CDN for Magento End User License Agreement
 * that is bundled with this package in the file LICENSE_FASTLY_CDN.txt.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade Fastly CDN to newer
 * versions in the future. If you wish to customize this module for your
 * needs please refer to http://www.magento.com for more information.
 *
 * @category    Fastly
 * @package     Fastly_Cdn
 * @copyright   Copyright (c) 2016 Fastly, Inc. (http://www.fastly.com)
 * @license     BSD, see LICENSE_FASTLY_CDN.txt
 */
namespace Fastly\Cdn\Console\Command;

use Fastly\Cdn\Model\Config;
use Fastly\Cdn\Model\Api;
use Fastly\Cdn\Helper\Vcl;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputOption;

class EnableCommand extends Command
{
    /**
     * @var Config
     */
    private $config;

    /**
     * @var Api
     */
    private $api;

    /**
     * @var Vcl
     */
    private $vcl;

    /**
     * @var \Magento\Framework\App\Config\Storage\WriterInterface
     */
    private $configWriter;

    /**
     * @var \Magento\Framework\App\Cache\Manager
     */
    private $cacheManager;

    /**
     * @var OutputInterface
     */
    private $output;

    /**
     * @inheritdoc
     */
    protected function configure() // @codingStandardsIgnoreLine - required by parent class
    {
        $this->setName('fastly:conf:set')
            ->setDescription('Enables Fastly as Full Page Cache Caching Application');

        $this->addOption(
            'enable',
            'e',
            InputOption::VALUE_NONE,
            'Enables Fastly Caching.'
        );

        $this->addOption(
            'disable',
            'd',
            InputOption::VALUE_NONE,
            'Disables Fastly Caching and sets build in cache as default cache.'
        );

        $this->addOption(
            'upload-vcl',
            'u',
            InputOption::VALUE_NONE,
            'Uploads default VCL files, Test connection must pass to proceed with VCL uploading.'
                . ' Add --activate argument to activate new version.'
        );

        $this->addOption(
            'enable-force-tls',
            'f',
            InputOption::VALUE_NONE,
            'Uploads Force TLS snippets, Test connection must pass to proceed with VCL uploading.'
                . ' Add --activate argument to activate new version.'
        );

        $this->addOption(
            'disable-force-tls',
            'l',
            InputOption::VALUE_NONE,
            'Removes Force TLS snippets, Test connection must pass to proceed with VCL snippet removal.'
                . ' Add --activate argument to activate new version.'
        );

        $this->addOption(
            'activate',
            'a',
            InputOption::VALUE_NONE,
            'Activate newly cloned version. Used with VCL upload.'
        );

        $this->addOption(
            'no',
            'N',
            InputOption::VALUE_NONE,
            'Disable option.'
        );

        $this->addOption(
            'test-connection',
            'c',
            InputOption::VALUE_NONE,
            'Tests connection with Fastly.'
        );

        $this->addOption(
            'cache',
            'o',
            InputOption::VALUE_NONE,
            'Cleanse Config Cache.'
        );

        $this->addOption(
            'service-id',
            's',
            InputOption::VALUE_REQUIRED,
            'Sets Fastly Service ID.',
            false
        );

        $this->addOption(
            'token',
            't',
            InputOption::VALUE_REQUIRED,
            'Sets Fastly Token.',
            false
        );

        $this->addOption(
            'admin-path-timeout',
            'A',
            InputOption::VALUE_REQUIRED,
            'Sets Fastly Admin Path Timeout',
            false
        );

        $this->addOption(
            'stale-content-delivery-time',
            'S',
            InputOption::VALUE_REQUIRED,
            'Time in seconds that Fastly will serve stale content while fresh content is being requested.',
            false
        );

        $this->addOption(
            'stale-content-delivery-time-error',
            'B',
            InputOption::VALUE_REQUIRED,
            'Time in seconds that Fastly will continue to serve stale content if your origin is unavailable.',
            false
        );

        $this->addOption(
            'ignored-url-parameters',
            'I',
            InputOption::VALUE_REQUIRED,
            'A comma separated list of ignored query string parameters.',
            false
        );

        $this->addOption(
            'purge-category',
            'C',
            InputOption::VALUE_NONE,
            'Choose to purge all the category assets when saving a change to that category.'
        );

        $this->addOption(
            'purge-product',
            'P',
            InputOption::VALUE_NONE,
            'Choose to purge all the product assets when saving a change to that product.'
        );

        $this->addOption(
            'purge-cms',
            'M',
            InputOption::VALUE_NONE,
            'Choose to purge page content when updating or adding a new page in the Magento CMS.'
        );

        $this->addOption(
            'preserve-static',
            'T',
            InputOption::VALUE_NONE,
            'When flushing cache, flush only dynamic content and preserve static assets.'
        );

        $this->addOption(
            'use-soft-purge',
            'F',
            InputOption::VALUE_NONE,
            'Soft Purge​ needs to be turned on in order to serve stale content.'
        );

        $this->addOption(
            'enable-geoip',
            'G',
            InputOption::VALUE_NONE,
            'Enable GeoIP for country/language lookup.'
        );

        $this->addOption(
            'geoip-action',
            'O',
            InputOption::VALUE_REQUIRED,
            'GeoIP Action option',
            false
        );
    }

    /**
     * @param Config $config
     */
    public function __construct(
        Config $config,
        Api $api,
        Vcl $vcl,
        \Magento\Framework\App\Config\Storage\WriterInterface $configWriter,
        \Magento\Framework\App\Cache\Manager $cacheManager
    ) {
        parent::__construct();
        $this->config = $config;
        $this->api = $api;
        $this->vcl = $vcl;
        $this->configWriter = $configWriter;
        $this->cacheManager = $cacheManager;
    }

    /**
     * @inheritdoc
     */
    protected function execute(InputInterface $input, OutputInterface $output) // @codingStandardsIgnoreLine - required by parent class
    {
        $this->output = $output;
        $options = $input->getOptions();

        if (count(array_unique($options)) === 1) {
            $this->output->writeln('<comment>' . $this->getSynopsis() . '</comment>', OutputInterface::OUTPUT_NORMAL);
            return;
        }

        // Set Service ID
        if ($input->getOption('service-id')) {
            $this->setServiceID($input->getOption('service-id'));
        }

        // Set Token
        if ($input->getOption('token')) {
            $this->setToken($input->getOption('token'));
        }

        // Set Admin Path Timeout
        if ($input->getOption('admin-path-timeout')) {
            $this->setAdminPathTimeout($input->getOption('admin-path-timeout'));
        }

        // Set Stale Content Delivery Time
        if ($input->getOption('stale-content-delivery-time')) {
            $this->setStaleContentDeliveryTime($input->getOption('stale-content-delivery-time'));
        }

        // Set Stale content delivery time in case of backend error
        if ($input->getOption('stale-content-delivery-time-error')) {
            $this->setStaleContentDeliveryTimeError($input->getOption('stale-content-delivery-time-error'));
        }

        // Set ignored url parameters
        if ($input->getOption('ignored-url-parameters')) {
            $this->setIgnoredUrlParameters($input->getOption('ignored-url-parameters'));
        }

        // Enable/disable purge category
        if ($input->getOption('purge-category')) {
            $this->setPurgeCategory($input->getOption('no'));
        }

        // Enable/disable purge product
        if ($input->getOption('purge-product')) {
            $this->setPurgeProduct($input->getOption('no'));
        }

        // Enable/disable purge CMS page
        if ($input->getOption('purge-cms')) {
            $this->setPurgeCms($input->getOption('no'));
        }

        // Enable/disable preserve static assets on purge
        if ($input->getOption('preserve-static')) {
            $this->setPreserveStatic($input->getOption('no'));
        }

        // Enable/disable use soft purge
        if ($input->getOption('use-soft-purge')) {
            $this->setUseSoftPurge($input->getOption('no'));
        }

        // Enable/disable enable geoip
        if ($input->getOption('enable-geoip')) {
            $this->setEnableGeoip($input->getOption('no'));
        }

        // Set geoip action
        if ($input->getOption('geoip-action')) {
            $this->setGeoipAction($input->getOption('geoip-action'));
        }

        // Upload VCL
        if ($input->getOption('upload-vcl')) {
            $this->uploadVcl($input->getOption('activate'));
        }

        // Enable Force TLS snippet
        if ($input->getOption('enable-force-tls')) {
            $this->enableforceTls($input->getOption('activate'));
        }

        // Enable Force TLS snippet
        if ($input->getOption('disable-force-tls')) {
            $this->disableforceTls($input->getOption('activate'));
        }

        // Enable
        if ($input->getOption('enable')) {
            $this->enableFastly();
        }

        // Disable
        if ($input->getOption('disable')) {
            $this->disableFastly();
        }

        // Test Connection
        if ($input->getOption('test-connection')) {
            $this->testConnection();
        }

        // Clean Configuration Cache
        if ($input->getOption('cache')) {
            $this->cleanCache();
        }
    }

    /**
     * Enable Fastly
     */
    private function enableFastly()
    {
        $this->configWriter->save('system/full_page_cache/caching_application', Config::FASTLY);
        $this->output->writeln(
            '<info>Fastly Caching Application Activated.</info>',
            OutputInterface::OUTPUT_NORMAL
        );
    }

    /**
     * Disable Fastly
     */
    private function disableFastly()
    {
        $this->configWriter->save(
            'system/full_page_cache/caching_application',
            \Magento\PageCache\Model\Config::BUILT_IN
        );
        $this->output->writeln(
            '<info>Fastly Caching Application Deactivated, default built in Caching mechanism set.</info>',
            OutputInterface::OUTPUT_NORMAL
        );
    }

    /**
     * Set Fastly Service ID
     * @param $serviceId
     */
    private function setServiceID($serviceId)
    {
        $this->configWriter->save(Config::XML_FASTLY_SERVICE_ID, $serviceId);
        $this->output->writeln(
            '<info>Service ID updated.</info>',
            OutputInterface::OUTPUT_NORMAL
        );
    }

    /**
     * Set Fastly API Token
     * @param $token
     */
    private function setToken($token)
    {
        $this->configWriter->save(Config::XML_FASTLY_API_KEY, $token);
        $this->output->writeln(
            '<info>Token updated.</info>',
            OutputInterface::OUTPUT_NORMAL
        );
    }

    /**
     * @param $adminPathTimeout
     */
    private function setAdminPathTimeout($adminPathTimeout)
    {
        if (!ctype_digit($adminPathTimeout)) {
            $this->output->writeln(
                '<error>The value must be an integer.</error>',
                OutputInterface::OUTPUT_NORMAL
            );
            return;
        }
        $this->configWriter->save(Config::XML_FASTLY_ADMIN_PATH_TIMEOUT, $adminPathTimeout);
        $this->output->writeln(
            '<info>Admin path timeout updated.</info>',
            OutputInterface::OUTPUT_NORMAL
        );
    }

    /**
     * @param $staleContentDeliveryTime
     */
    private function setStaleContentDeliveryTime($staleContentDeliveryTime)
    {
        if (!ctype_digit($staleContentDeliveryTime)) {
            $this->output->writeln(
                '<error>The value must be an integer.</error>',
                OutputInterface::OUTPUT_NORMAL
            );
            return;
        }
        $this->configWriter->save(Config::XML_FASTLY_STALE_TTL, $staleContentDeliveryTime);
        $this->output->writeln(
            '<info>Stale content delivery time updated.</info>',
            OutputInterface::OUTPUT_NORMAL
        );
    }

    /**
     * @param $staleContentDeliveryTimeError
     */
    private function setStaleContentDeliveryTimeError($staleContentDeliveryTimeError)
    {
        if (!ctype_digit($staleContentDeliveryTimeError)) {
            $this->output->writeln(
                '<error>The value must be an integer.</error>',
                OutputInterface::OUTPUT_NORMAL
            );
            return;
        }
        $this->configWriter->save(Config::XML_FASTLY_STALE_ERROR_TTL, $staleContentDeliveryTimeError);
        $this->output->writeln(
            '<info>Stale content delivery time in case of backend error updated.</info>',
            OutputInterface::OUTPUT_NORMAL
        );
    }

    /**
     * @param $ignoredUrlParameters
     */
    private function setIgnoredUrlParameters($ignoredUrlParameters)
    {
        $this->configWriter->save(Config::XML_FASTLY_IGNORED_URL_PARAMETERS, $ignoredUrlParameters);
        $this->output->writeln(
            '<info>Ignored Url Parameters updated.</info>',
            OutputInterface::OUTPUT_NORMAL
        );
    }

    /**
     * @param $no
     */
    private function setPurgeCategory($no)
    {
        if ($no) {
            $this->configWriter->save(Config::XML_FASTLY_PURGE_CATALOG_CATEGORY, 0);
            $this->output->writeln(
                '<info>Purge category disabled.</info>',
                OutputInterface::OUTPUT_NORMAL
            );
        } else {
            $this->configWriter->save(Config::XML_FASTLY_PURGE_CATALOG_CATEGORY, 1);
            $this->output->writeln(
                '<info>Purge category enabled. To disable add the --no argument.</info>',
                OutputInterface::OUTPUT_NORMAL
            );
        }
    }

    /**
     * @param $no
     */
    private function setPurgeProduct($no)
    {
        if ($no) {
            $this->configWriter->save(Config::XML_FASTLY_PURGE_CATALOG_PRODUCT, 0);
            $this->output->writeln(
                '<info>Purge product disabled.</info>',
                OutputInterface::OUTPUT_NORMAL
            );
        } else {
            $this->configWriter->save(Config::XML_FASTLY_PURGE_CATALOG_PRODUCT, 1);
            $this->output->writeln(
                '<info>Purge product enabled. To disable add the --no argument.</info>',
                OutputInterface::OUTPUT_NORMAL
            );
        }
    }

    /**
     * @param $no
     */
    private function setPurgeCms($no)
    {
        if ($no) {
            $this->configWriter->save(Config::XML_FASTLY_PURGE_CMS_PAGE, 0);
            $this->output->writeln(
                '<info>Purge CMS page disabled.</info>',
                OutputInterface::OUTPUT_NORMAL
            );
        } else {
            $this->configWriter->save(Config::XML_FASTLY_PURGE_CMS_PAGE, 1);
            $this->output->writeln(
                '<info>Purge CMS page enabled. To disable add the --no argument.</info>',
                OutputInterface::OUTPUT_NORMAL
            );
        }
    }

    /**
     * @param $no
     */
    private function setPreserveStatic($no)
    {
        if ($no) {
            $this->configWriter->save(Config::XML_FASTLY_PRESERVE_STATIC, 0);
            $this->output->writeln(
                '<info>Preserve static assets on purge disabled.</info>',
                OutputInterface::OUTPUT_NORMAL
            );
        } else {
            $this->configWriter->save(Config::XML_FASTLY_PRESERVE_STATIC, 1);
            $this->output->writeln(
                '<info>Preserve static assets on purge enabled. To disable add the --no argument.</info>',
                OutputInterface::OUTPUT_NORMAL
            );
        }
    }

    /**
     * @param $no
     */
    private function setUseSoftPurge($no)
    {
        if ($no) {
            $this->configWriter->save(Config::XML_FASTLY_SOFT_PURGE, 0);
            $this->output->writeln(
                '<info>Use Soft Purge disabled.</info>',
                OutputInterface::OUTPUT_NORMAL
            );
        } else {
            $this->configWriter->save(Config::XML_FASTLY_SOFT_PURGE, 1);
            $this->output->writeln(
                '<info>Use Soft Purge enabled. To disable add the --no argument.</info>',
                OutputInterface::OUTPUT_NORMAL
            );
        }
    }

    /**
     * @param $no
     */
    private function setEnableGeoip($no)
    {
        if ($no) {
            $this->configWriter->save(Config::XML_FASTLY_GEOIP_ENABLED, 0);
            $this->output->writeln(
                '<info>GeoIP disabled.</info>',
                OutputInterface::OUTPUT_NORMAL
            );
        } else {
            $this->configWriter->save(Config::XML_FASTLY_GEOIP_ENABLED, 1);
            $this->output->writeln(
                '<info>GeoIP enabled. To disable add the --no argument.</info>',
                OutputInterface::OUTPUT_NORMAL
            );
        }
    }

    /**
     * @param $geoipAction
     */
    private function setGeoipAction($geoipAction)
    {
        if (strtolower($geoipAction) === 'dialog') {
            $this->configWriter->save(Config::XML_FASTLY_GEOIP_ACTION, $geoipAction);
            $this->output->writeln(
                '<info>GeoIP Action set to dialog.</info>',
                OutputInterface::OUTPUT_NORMAL
            );
        } elseif (strtolower($geoipAction) === 'redirect') {
            $this->configWriter->save(Config::XML_FASTLY_GEOIP_ACTION, $geoipAction);
            $this->output->writeln(
                '<info>GeoIP Action set to redirect.</info>',
                OutputInterface::OUTPUT_NORMAL
            );
        } else {
            $this->output->writeln(
                '<error>This option requires "dialog" or "redirect" as a value.</error>',
                OutputInterface::OUTPUT_NORMAL
            );
        }
    }

    private function cleanCache()
    {
        $this->cacheManager->clean([\Magento\Framework\App\Cache\Type\Config::TYPE_IDENTIFIER]);
        $this->output->writeln(
            '<info>Configuration Cache Cleaned.</info>',
            OutputInterface::OUTPUT_NORMAL
        );
    }

    /**
     * Upload default VCL, conditions and requests
     * @param $activate
     * @throws \Exception
     * @throws \Magento\Framework\Exception\FileSystemException
     */
    private function uploadVcl($activate)
    {
        $service = $this->api->checkServiceDetails();

        if (!$service) {
            $this->output->writeln(
                '<error>Failed to check Service details. Possible connection issues.</error>',
                OutputInterface::OUTPUT_NORMAL
            );
            return;
        }

        $currActiveVersion = $this->vcl->determineVersions($service->versions);

        $clone = $this->api->cloneVersion($currActiveVersion['active_version']);

        if (!$clone) {
            $this->output->writeln(
                '<error>Failed to clone active version.</error>',
                OutputInterface::OUTPUT_NORMAL
            );
            return;
        }

        $snippets = $this->config->getVclSnippets();
        $ignoredUrlParameters = $this->config->getIgnoredUrlParameters();
        $adminPathTimeout = $this->config->getAdminPathTimeout();
        $adminUrl = $this->vcl->getAdminFrontName();

        $ignoredUrlParameterPieces = explode(",", $ignoredUrlParameters);
        $filterIgnoredUrlParameterPieces = array_filter(array_map('trim', $ignoredUrlParameterPieces));
        $queryParameters = implode('|', $filterIgnoredUrlParameterPieces);

        foreach ($snippets as $key => $value) {
            $value = str_replace('####ADMIN_PATH####', $adminUrl, $value);
            $value = str_replace('####ADMIN_PATH_TIMEOUT####', $adminPathTimeout, $value);
            $value = str_replace('####QUERY_PARAMETERS####', $queryParameters, $value);
            $snippetData = [
                'name'      => Config::FASTLY_MAGENTO_MODULE . '_' . $key,
                'type'      => $key,
                'dynamic'   => "0",
                'priority'  => 50,
                'content'   => $value
            ];
            $status = $this->api->uploadSnippet($clone->number, $snippetData);

            if (!$status) {
                $this->output->writeln(
                    '<error>Failed to upload the Snippet file.</error>',
                    OutputInterface::OUTPUT_NORMAL
                );
                return;
            }
        }

        $condition = [
            'name'      => Config::FASTLY_MAGENTO_MODULE . '_pass',
            'statement' => 'req.http.x-pass',
            'type'      => 'REQUEST',
            'priority'  => 90
        ];
        $createCondition = $this->api->createCondition($clone->number, $condition);

        if (!$createCondition) {
            $this->output->writeln(
                '<error>Failed to create a REQUEST condition.</error>',
                OutputInterface::OUTPUT_NORMAL
            );
            return;
        }

        $request = [
            'action'            => 'pass',
            'max_stale_age'     => 3600,
            'name'              => Config::FASTLY_MAGENTO_MODULE.'_request',
            'request_condition' => $createCondition->name,
            'service_id'        => $service->id,
            'version'           => $currActiveVersion['active_version']
        ];

        $createReq = $this->api->createRequest($clone->number, $request);

        if (!$createReq) {
            $this->output->writeln(
                '<error>Failed to create a REQUEST object.</error>',
                OutputInterface::OUTPUT_NORMAL
            );
            return;
        }

        $validate = $this->api->validateServiceVersion($clone->number);

        if ($validate->status == 'error') {
            $this->output->writeln(
                '<error>Failed to validate service version: '. $validate->msg . '</error>',
                OutputInterface::OUTPUT_NORMAL
            );
            return;
        }

        $msg = 'Successfully uploaded VCL. ';

        if ($activate) {
            $this->api->activateVersion($clone->number);
            $msg .= 'Activated Version '. $clone->number;
        }

        if ($this->config->areWebHooksEnabled() && $this->config->canPublishConfigChanges()) {
            $this->api->sendWebHook('*Upload VCL has been initiated and activated in version ' . $clone->number . '*');
        }

        $this->output->writeln('<info>' . $msg . '</info>', OutputInterface::OUTPUT_NORMAL);
    }

    /**
     *  Uploads the Force TLS VCL snippet
     * @param $activate
     */
    private function enableForceTls($activate)
    {
        $service = $this->api->checkServiceDetails();

        if (!$service) {
            $this->output->writeln(
                '<error>Failed to check Service details. Possible connection issues.</error>',
                OutputInterface::OUTPUT_NORMAL
            );

            return;
        }

        $currActiveVersion = $this->vcl->determineVersions($service->versions);
        $clone = $this->api->cloneVersion($currActiveVersion['active_version']);

        if (!$clone) {
            $this->output->writeln(
                '<error>Failed to clone active version.</error>',
                OutputInterface::OUTPUT_NORMAL
            );

            return;
        }

        $reqName = Config::FASTLY_MAGENTO_MODULE.'_force_tls';
        $snippet = $this->config->getVclSnippets('/vcl_snippets_force_tls', 'recv.vcl');

            $request = [
                'name'          => $reqName,
                'service_id'    => $service->id,
                'version'       => $currActiveVersion['active_version'],
                'force_ssl'     => true
            ];

            $createReq = $this->api->createRequest($clone->number, $request);

            if (!$createReq) {
                $this->output->writeln(
                    '<error>Failed to create a REQUEST object.</error>',
                    OutputInterface::OUTPUT_NORMAL
                );

                return;
            }

            // Add force TLS snippet
            foreach ($snippet as $key => $value) {
                $snippetData = [
                    'name'      => Config::FASTLY_MAGENTO_MODULE.'_force_tls_'.$key,
                    'type'      => $key, 'dynamic' => "0",
                    'priority'  => 10,
                    'content'   => $value
                ];
                $status = $this->api->uploadSnippet($clone->number, $snippetData);

                if (!$status) {
                    $this->output->writeln(
                        '<error>Failed to upload the Snippet file.</error>',
                        OutputInterface::OUTPUT_NORMAL
                    );

                    return;
                }
            }

            $validate = $this->api->validateServiceVersion($clone->number);

            if ($validate->status == 'error') {
                $this->output->writeln(
                    '<error>Failed to validate service version: '. $validate->msg . '</error>',
                    OutputInterface::OUTPUT_NORMAL
                );

                return;
            }

            $msg = 'Successfully uploaded Force TLS VCL snippet. ';

            if ($activate) {
                $this->api->activateVersion($clone->number);
                $msg .= 'Activated Version '. $clone->number;
            }

            if ($this->config->areWebHooksEnabled() && $this->config->canPublishConfigChanges()) {
                    $this->api->sendWebHook('*Force TLS has been turned ON in Fastly version '. $clone->number . '*');
            }

            $this->output->writeln('<info>' . $msg . '</info>', OutputInterface::OUTPUT_NORMAL);
    }

    /**
     * Removes the Force TLS VCL snippet from the current active version
     * @param $activate
     */
    private function disableForceTls($activate)
    {
        $service = $this->api->checkServiceDetails();

        if (!$service) {
            $this->output->writeln(
                '<error>Failed to check Service details. Possible connection issues.</error>',
                OutputInterface::OUTPUT_NORMAL
            );

            return;
        }

        $currActiveVersion = $this->vcl->determineVersions($service->versions);

        $clone = $this->api->cloneVersion($currActiveVersion['active_version']);

        if (!$clone) {
            $this->output->writeln(
                '<error>Failed to clone active version.</error>',
                OutputInterface::OUTPUT_NORMAL
            );

            return;
        }
        $reqName = Config::FASTLY_MAGENTO_MODULE.'_force_tls';
        $snippet = $this->config->getVclSnippets('/vcl_snippets_force_tls', 'recv.vcl');

        $request = [
            'name'          => $reqName,
            'service_id'    => $service->id,
            'version'       => $currActiveVersion['active_version'],
            'force_ssl'     => true
        ];

        $createReq = $this->api->createRequest($clone->number, $request);

        if (!$createReq) {
            $this->output->writeln(
                '<error>Failed to create a REQUEST object.</error>',
                OutputInterface::OUTPUT_NORMAL
            );

            return;
        }

        $deleteRequest = $this->api->deleteRequest($clone->number, $reqName);

        if (!$deleteRequest) {
            $this->output->writeln(
                '<error>Failed to delete the REQUEST object.</error>',
                OutputInterface::OUTPUT_NORMAL
            );

            return;
        }

        // Remove Force TLS snippet
        foreach ($snippet as $key => $value) {
            $name = Config::FASTLY_MAGENTO_MODULE.'_force_tls_'.$key;
            $status = $this->api->removeSnippet($clone->number, $name);

            if (!$status) {
                $this->output->writeln(
                    '<error>Failed to remove the Snippet file.</error>',
                    OutputInterface::OUTPUT_NORMAL
                );

                return;
            }
        }

        $validate = $this->api->validateServiceVersion($clone->number);

        if ($validate->status == 'error') {
            $this->output->writeln(
                '<error>Failed to validate service version: '. $validate->msg . '</error>',
                OutputInterface::OUTPUT_NORMAL
            );

            return;
        }

        $msg = 'Successfully removed Force TLS VCL snippet. ';

        if ($activate) {
            $this->api->activateVersion($clone->number);
            $msg .= 'Activated Version '. $clone->number;
        }

        if ($this->config->areWebHooksEnabled() && $this->config->canPublishConfigChanges()) {
            $this->api->sendWebHook('*Force TLS has been turned OFF in Fastly version '. $clone->number . '*');
        }

        $this->output->writeln('<info>' . $msg . '</info>', OutputInterface::OUTPUT_NORMAL);
    }

    private function testConnection()
    {
        $service = $this->api->checkServiceDetails();

        if (!$service) {
            $this->output->writeln(
                '<error>Status: Connection failed, check your Token and Service ID credentials.</error>',
                OutputInterface::OUTPUT_NORMAL
            );

            return;
        }

        $currActiveVersion = $this->vcl->determineVersions($service->versions);
        $this->output->writeln(
            '<info>Status: Connection Successful, current active version: '
            . $currActiveVersion['active_version']
            . '</info>',
            OutputInterface::OUTPUT_NORMAL
        );
    }
}