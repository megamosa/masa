<?php
/**
 * @package   MagoArab_HideMassActions
 * @author    MagoArab
 * @copyright Copyright (c) 2025 MagoArab (https://www.magoarab.com)
 */

namespace MagoArab\HideMassActions\Plugin;

use Magento\Framework\AuthorizationInterface;
use Mageplaza\MassOrderActions\Model\Config\Source\System\Actions;
use Mageplaza\MassOrderActions\Ui\Component\Listing\Column\ViewAction;

class ViewActionPermissionPlugin
{
    /**
     * @var \MagoArab\HideMassActions\Helper\Data
     */
    private $helper;

    /**
     * @param \MagoArab\HideMassActions\Helper\Data $helper
     */
    public function __construct(
        \MagoArab\HideMassActions\Helper\Data $helper
    ) {
        $this->helper = $helper;
    }

    /**
     * Filter individual row actions based on permissions
     *
     * @param ViewAction $subject
     * @param array $result
     * @param array $dataSource
     * @return array
     */
    public function afterPrepareDataSource(
        ViewAction $subject,
        array $result
    ) {
        if (isset($result['data']['items'])) {
            foreach ($result['data']['items'] as &$item) {
                if (isset($item['actions'])) {
                    // Filter row-level actions based on permissions
                    foreach ($item['actions'] as $actionKey => $action) {
                        // Check permissions using helper
                        $allowed = $this->helper->isActionAllowed($actionKey);
                        
                        // Remove action if not allowed
                        if (!$allowed) {
                            unset($item['actions'][$actionKey]);
                        }
                    }
                }
            }
        }
        
        return $result;
    }
}