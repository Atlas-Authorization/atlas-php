<?php

declare(strict_types=1);

namespace Atlas\Resources;

/** `/v1/risk_based_mfa` — the adaptive/risk-based MFA engine config. */
final class RiskBasedMfa extends Resource
{
    /** @return array<string,mixed> */
    public function get(): array
    {
        return $this->http->request('GET', '/v1/risk_based_mfa');
    }

    /**
     * @param array{enabled?:bool,step_up_threshold?:string,on_high_risk_no_factor?:string,weights?:array<string,mixed>,signals?:array<string,mixed>} $body
     *
     * @return array<string,mixed>
     */
    public function update(array $body): array
    {
        return $this->http->request('PATCH', '/v1/risk_based_mfa', body: $body);
    }
}
