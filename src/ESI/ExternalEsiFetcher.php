<?php

namespace EK\ESI;

class ExternalEsiFetcher
{
    // @TODO This class is for implementing the fetcher that wraps around the EsiFetcher
    // This should be used for all ESI Passthrough Requests (Via the /latest /dev /v1 /v2 etc. endpoints
    // So we can track users and their requests
    // To ensure that malicious users can be banned

    // THIS IS NOT to track what people are requesting
    // It should not track headers, request bodies or request parameters
    // It should hash the data, and generate a tracking ID that is also emitted to the esi.log
}