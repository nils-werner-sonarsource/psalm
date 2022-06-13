<?php

namespace Psalm\Tests;

class SuperGlobalsTest extends TestCase
{
    use Traits\ValidCodeAnalysisTestTrait;

    /**
     * @return iterable<string,array{string,assertions?:array<string,string>,error_levels?:string[]}>
     */
    public function providerValidCodeParse(): iterable
    {
        yield 'http_response_headerIsList' => [
            '<?php
                /** @return list<string> */
                function returnsList(): array {
                    return $http_response_header;
                }
            ',
            'assertions' => []
        ];
    }
}
