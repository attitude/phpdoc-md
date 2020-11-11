<?php namespace Clean\PhpDocMd;

use phpDocumentor\Reflection\DocBlockFactory;
use ReflectionClass;
use ReflectionMethod;

class ClassParser
{
    /**
     * @var ReflectionClass
     */
    private $reflection;
    private $docBlockFactory;

    public function __construct(ReflectionClass $class)
    {
        $this->reflection = $class;
        $this->docBlockFactory = DocBlockFactory::createInstance();
    }

    public function getClassDescription()
    {
        $docblock = $this->docBlockFactory->create($this->reflection->getDocComment() ?: '/** */');
        return (object)[
            'short' => static::toSingleLine((string)$docblock->getSummary()),
            'long' => (string)$docblock->getDescription(),
        ];
    }

    public function getParentClassName()
    {
        return ($p = $this->reflection->getParentClass()) ? $p->getName() : null;
    }

    public function getInterfaces()
    {
        return $this->reflection->getInterfaceNames();
    }

    public function getMethodsDetails()
    {
        $methods = [];
        $parentClassMethods = $this->getInheritedMethods();

        foreach ($this->reflection->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            if (isset($parentClassMethods[$method->getName()])) {
                continue;
            }
            $methods[$method->getName()] = $this->getMethodDetails($method);
        }

        return $methods;
    }

    public function getConstantsDetails()
    {
        $constants = [];
        $parentClassConstants = $this->getInheritedConstants();

        foreach ($this->reflection->getConstants() as $constant => $value) {
            if (isset($parentClassConstants[$constant])) {
                continue;
            }

            $constants[$constant] = $this->getConstantDetails($constant);
        }

        return $constants;
    }

    private static function toSingleLine($string) {
        return preg_replace('/\s+/', ' ', $string);
    }

    private function getMethodDetails($method)
    {
        $docblock = $this->docBlockFactory->create($method->getDocComment() ?: '/** */');

        $data = [
            'shortDescription' => null,
            'longDescription' => null,
            'argumentsList' => [],
            'argumentsDescription' => null,
            'returnValue' => null,
            'throwsExceptions' => null,
            'visibility' => null,
            'type' => null,
        ];

        if ($docblock->getSummary()) {
            $data['shortDescription'] = static::toSingleLine($docblock->getSummary());
            $data['longDescription'] = $docblock->getDescription();
            $data['argumentsList'] = $this->retrieveParams($docblock->getTagsByName('param'));
            $data['argumentsDescription'] = $this->retrieveParamsDescription($docblock->getTagsByName('param'));
            $data['returnValue'] = $this->retrieveTagData($docblock->getTagsByName('return'));
            $data['throwsExceptions'] = $this->retrieveTagData($docblock->getTagsByName('throws'));
            $data['visibility'] =  join(
                    '',
                    [
                        $method->isFinal() ? 'final ' : '',
                        'public',
                        $method->isStatic() ? ' static' : '',
                    ]
                );
            $data['type'] = $method->isStatic() ? '::' : '->';
        } else {
            $className = sprintf("%s::%s", $method->class, $method->name);
            $atlasdoc = new \Clean\PhpAtlas\ClassMethod($className);
            $data['shortDescription'] = static::toSingleLine($atlasdoc->getMethodShortDescription());
            $data['doclink'] = $atlasdoc->getMethodPHPDocLink();
            $data['type'] = $method->isStatic() ? '::' : '->';
        }
        return (object)$data;
    }

    public function getInheritedMethods()
    {
        $methods = [];
        $parentClass = $this->reflection->getParentClass();
        if ($parentClass) {
            foreach ($parentClass->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
                $methods[$method->getName()] = $this->getMethodDetails($method);
            }
        }
        ksort($methods);
        return $methods;
    }

    public function getConstantDetails($constant)
    {
        $reflection = $this->reflection->getReflectionConstant($constant);
        $docblock = $this->docBlockFactory->create($reflection->getDocComment() ?: '/** */');

        return (object)[
            'short' => (string)static::toSingleLine($docblock->getSummary()),
            'long' => (string)$docblock->getDescription(),
            'value' => is_scalar($reflection->getValue())
                ? json_encode($reflection->getValue())
                : gettype($reflection->getValue()),
        ];
    }

    public function getInheritedConstants()
    {
        $constants = [];
        $parentClass = $this->reflection->getParentClass();

        if ($parentClass) {
            foreach ($parentClass->getConstants() as $constant) {
                $constants[$constant->getName()] = $this->getMethodDetails($constant);
            }
        }
        ksort($constants);
        return $constants;
    }

    private function retrieveTagData(array $params)
    {
        $data = [];
        foreach ($params as $param) {
            $data[] = (object)[
                'desc' => static::toSingleLine($param->getDescription()),
                'type' => $param->getType(),
            ];
        }
        return $data;
    }

    private function retrieveParams(array $params)
    {
        $data = [];
        foreach ($params as $param) {
            $data[] = sprintf("%s $%s", $param->getType(), $param->getVariableName());
        }
        return $data;
    }

    private function retrieveParamsDescription(array $params)
    {
        $data = [];
        foreach ($params as $param) {
            $data[] = (object)[
                'name' => '$' . $param->getVariableName(),
                'desc' => static::toSingleLine($param->getDescription()),
                'type' => $param->getType(),
            ];
        }
        return $data;
    }

    private function getPHPDocLink($method) {
        return strtolower(sprintf('https://secure.php.net/manual/en/%s.%s.php', $method->class, $method->name));
    }
}
