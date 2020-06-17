<?php declare(strict_types=1);

namespace Chaplean\Bundle\DtoHandlerBundle\Serializer;

use Chaplean\Bundle\DtoHandlerBundle\Annotation\Field;
use Chaplean\Bundle\DtoHandlerBundle\Annotation\MapTo;
use Chaplean\Bundle\DtoHandlerBundle\DataTransferObject\DataTransferObjectInterface;
use Doctrine\Common\Annotations\AnnotationReader;
use Symfony\Component\PropertyAccess\PropertyAccess;
use Symfony\Component\PropertyAccess\PropertyAccessor;
use Symfony\Component\Serializer\Exception\ExceptionInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Symfony\Component\Serializer\Serializer;

/**
 * Class DataTransferObjectNormalizer
 *
 * @package   Chaplean\Bundle\DtoHandlerBundle\Serializer
 * @author    Nicolas Guilloux <nguilloux@richcongress.com>
 * @copyright 2014 - 2020 RichCongress (https://www.richcongress.com)
 */
class DataTransferObjectNormalizer implements NormalizerInterface
{
    /**
     * @var AnnotationReader
     */
    protected $annotationReader;

    /**
     * @var PropertyAccessor
     */
    protected $propertyAccessor;

    /**
     * @var Serializer
     */
    protected $serializer;

    /**
     * DataTransferObjectNormalizer constructor.
     *
     * @param Serializer $serializer
     */
    public function __construct(Serializer $serializer)
    {
        $this->annotationReader = new AnnotationReader();
        $this->propertyAccessor = PropertyAccess::createPropertyAccessor();
        $this->serializer = $serializer;
    }

    /**
     * @param mixed       $data
     * @param string|null $format
     * @param array       $context
     *
     * @return array
     *
     * @throws \ReflectionException
     * @throws ExceptionInterface
     */
    public function normalize($data, $format = null, array $context = []): array
    {
        $reflectionClass = new \ReflectionClass($data);
        $body = [];

        foreach ($reflectionClass->getProperties(\ReflectionProperty::IS_PUBLIC) as $reflectionProperty) {
            $key = $this->getKey($reflectionProperty);
            $value = $this->getValue($reflectionProperty, $data);

            $body[$key] = $value;
        }

        $subContext = $context;
        $subContext[EntityIdNormalizer::class] = true;

        return $this->serializer->normalize($body, $format, $subContext);
    }

    /**
     * @param mixed $data
     * @param null  $format
     *
     * @return bool
     */
    public function supportsNormalization($data, $format = null): bool
    {
        return is_object($data) && $data instanceof DataTransferObjectInterface;
    }

    /**
     * @param \ReflectionProperty $property
     *
     * @return string
     */
    protected function getKey(\ReflectionProperty $property): string
    {
        /** @var Field|null $fieldAnnotation */
        $fieldAnnotation = $this->annotationReader->getPropertyAnnotation($property, Field::class);

        return $fieldAnnotation !== null
            ? $fieldAnnotation->keyname
            : $property->getName();
    }

    /**
     * @param \ReflectionProperty $property
     * @param mixed               $data
     *
     * @return mixed
     */
    protected function getValue(\ReflectionProperty $property, $data)
    {
        /** @var MapTo|null $mapToAnnotation */
        $mapToAnnotation = $this->annotationReader->getPropertyAnnotation($property, MapTo::class);
        $value = $property->getValue($data);

        if ($mapToAnnotation === null) {
            return $value;
        }

        return $this->propertyAccessor->getValue(
            $value,
            $mapToAnnotation->keyname
        );
    }
}