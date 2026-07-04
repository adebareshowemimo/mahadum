<?php

return [
    /*
     * IRI namespace for xAPI activity objects (lessons, courses, questions …)
     * and the actor account homePage. Learner agents are identified by an
     * account name (`learner:<id>`) rather than email, since child profiles have
     * no independent login.
     */
    'base_iri' => env('XAPI_BASE_IRI', 'https://mahadum360.com/xapi'),
    'actor_homepage' => env('XAPI_ACTOR_HOMEPAGE', 'https://mahadum360.com'),
];
