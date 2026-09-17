<?php
declare(strict_types=1);

/**
 * Upgrade policy for HTTP Beacon URLs that predate HTTP Strictness.
 */
class BasicRum_Analytics_Model_Setup_HttpPolicyDefault
{
    /**
     * Return scoped policy values for existing Beacon URLs.
     *
     * Existing explicit HTTP-policy values always win. Invalid and duplicate
     * rows are ignored so the installer cannot write outside Magento scopes.
     *
     * @param array<int, array<string, mixed>> $beaconRows
     * @param array<int, array<string, mixed>> $explicitPolicyRows
     * @return array<int, array{scope: string, scope_id: int, value: string}>
     */
    public static function getValuesToPersist(array $beaconRows, array $explicitPolicyRows): array
    {
        $explicitScopes = array();
        foreach ($explicitPolicyRows as $row) {
            $key = self::getScopeKey($row);
            if ($key !== null) {
                $explicitScopes[$key] = true;
            }
        }

        $scopes = array();
        foreach ($beaconRows as $row) {
            $key = self::getScopeKey($row);
            $value = isset($row['value']) ? trim((string) $row['value']) : '';

            if ($key === null || isset($explicitScopes[$key]) || isset($scopes[$key])) {
                continue;
            }

            if (stripos($value, 'http://') === 0) {
                $policyValue = '1';
            } elseif (stripos($value, 'https://') === 0) {
                $policyValue = '0';
            } else {
                continue;
            }

            $scopes[$key] = array(
                'scope' => (string) $row['scope'],
                'scope_id' => (int) $row['scope_id'],
                'value' => $policyValue,
            );
        }

        return array_values($scopes);
    }

    /**
     * @param array<string, mixed> $row
     * @return string|null
     */
    private static function getScopeKey(array $row)
    {
        $allowedScopes = array('default', 'websites', 'stores');
        $scope = isset($row['scope']) ? (string) $row['scope'] : '';
        $scopeId = isset($row['scope_id']) ? filter_var($row['scope_id'], FILTER_VALIDATE_INT) : false;

        if (!in_array($scope, $allowedScopes, true) || $scopeId === false || (int) $scopeId < 0) {
            return null;
        }

        if (($scope === 'default' && (int) $scopeId !== 0)
            || ($scope !== 'default' && (int) $scopeId === 0)
        ) {
            return null;
        }

        return $scope . ':' . (int) $scopeId;
    }
}
