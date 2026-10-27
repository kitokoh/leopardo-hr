<?php

declare(strict_types=1);

/*
 * #8230 (BOS-042) — interrupteur du parcours Lea.
 *
 * Le flag est evalue COTE SERVEUR : `LeaOnboardingController` rend 404 quand
 * il est desactive. Un flag uniquement lu par le front ne serait pas un
 * interrupteur — une route joignable resterait capable d'activer des modules
 * alors que « la fonctionnalite est eteinte ».
 *
 * OFF par defaut : le parcours Lea introduit un appel LLM payant dans le
 * funnel d'inscription. L'activer releve d'une decision d'exploitation (et
 * du pilote mesure de BOS-044), pas d'un defaut de configuration.
 */
return [

    'lea' => [
        'enabled' => (bool) env('ONBOARDING_LEA_ENABLED', false),
    ],

];
