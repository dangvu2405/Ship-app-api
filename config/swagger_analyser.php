<?php

use OpenApi\Analysers\AttributeAnnotationFactory;
use OpenApi\Analysers\DocBlockAnnotationFactory;
use OpenApi\Analysers\ReflectionAnalyser;

return new ReflectionAnalyser([
    new AttributeAnnotationFactory(false),
    new DocBlockAnnotationFactory,
]);
