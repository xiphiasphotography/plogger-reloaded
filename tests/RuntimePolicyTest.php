<?php
use PHPUnit\Framework\TestCase;
require_once dirname(__DIR__).'/tools/runtime-policy.php';

final class RuntimePolicyTest extends TestCase {
    public function testOldAndUnknownRuntimesNeverReceiveStableApproval(): void {
        foreach (['8.1.34','8.2.34','8.3.35','8.4.26','8.5.11','8.6.0RC3','8.6.0','8.7.0','8.5.12-dev','8.2.34-vendor-backport'] as $version) {
            self::assertSame('unsupported', plogger_runtime_policy($version)['state'], $version);
        }
    }
    public function testStablePatchFloorsAndPrereleaseAreDistinct(): void {
        foreach (['8.2.35','8.3.36','8.4.27','8.5.12'] as $version) self::assertSame('stable',plogger_runtime_policy($version)['state']);
        self::assertSame('prerelease',plogger_runtime_policy('8.6.0RC4')['state']);
        self::assertStringContainsString('not a production-supported',plogger_runtime_policy('8.6.0RC4')['message']);
    }
}
