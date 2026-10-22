<?php
/** Numeric upstream patch floors, not a guarantee about untested future versions.
 * @return array{state:string,message:string} */
function plogger_runtime_policy(string $version): array {
    if (strcasecmp($version, '8.6.0RC4') === 0) {
        return ['state'=>'prerelease','message'=>'PHP 8.6.0RC4: experimental compatibility testing only; not a production-supported runtime.'];
    }
    $floors = ['8.2'=>35, '8.3'=>36, '8.4'=>27, '8.5'=>12];
    if (preg_match('/^(8\.[2-5])\.([0-9]+)$/D', $version, $parts)
        && (int) $parts[2] >= $floors[$parts[1]]) {
        return ['state'=>'stable','message'=>'Upstream security patch floor satisfied. Check the recorded compatibility matrix and current vendor advisories.'];
    }
    return ['state'=>'unsupported','message'=>'Use PHP 8.2.35+, 8.3.36+, 8.4.27+ or 8.5.12+. Older vendor builds require independently documented backport verification; this check does not approve exceptions.'];
}
