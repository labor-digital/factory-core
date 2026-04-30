<?php

declare(strict_types=1);

defined('TYPO3') or die();

// Tenant-scope DataHandler guardrails (shared-tenant mode).
// The enforcer is a no-op on global admins, so leaving it registered in
// dedicated-instance mode is safe.
$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['t3lib/class.t3lib_tcemain.php']['processDatamapClass'][] =
    \LaborDigital\FactoryCore\Security\TenantScopeEnforcer::class;
$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['t3lib/class.t3lib_tcemain.php']['processCmdmapClass'][] =
    \LaborDigital\FactoryCore\Security\TenantScopeEnforcer::class;
