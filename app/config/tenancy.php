<?php

return [
    /*
    | Domínio base para detectar o tenant por subdomínio: com TENANT_BASE_DOMAIN=app.com,
    | "acme.app.com" identifica a empresa de slug "acme". Vazio = sem detecção por subdomínio
    | (o login mostra a lista de empresas, ou aceita ?empresa=slug).
    */
    'base_domain' => env('TENANT_BASE_DOMAIN'),
];
