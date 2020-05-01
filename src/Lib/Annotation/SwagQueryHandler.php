<?php

namespace SwaggerBake\Lib\Annotation;

use SwaggerBake\Lib\OpenApi\Parameter;
use SwaggerBake\Lib\OpenApi\Schema;

class SwagQueryHandler
{
    public function getQueryParameter(SwagQuery $annotation) : Parameter
    {
        $parameter = new Parameter();
        $parameter
            ->setName($annotation->name)
            ->setDescription($annotation->description)
            ->setAllowEmptyValue(false)
            ->setDeprecated(false)
            ->setRequired($annotation->required)
            ->setIn('query')
            ->setSchema((new Schema())->setType($annotation->type));

        return $parameter;
    }
}