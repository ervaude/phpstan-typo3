<?php

declare(strict_types=1);

namespace FriendsOfTYPO3\PHPStan\TYPO3\Type;

use PhpParser\Node\Arg as ArgumentNode;
use PhpParser\Node\Expr\ClassConstFetch;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Name;
use PhpParser\Node\Scalar\String_ as StringNode;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\ClassReflection;
use PHPStan\Reflection\MethodReflection;
use PHPStan\Type\DynamicMethodReturnTypeExtension;
use PHPStan\Type\ObjectType;
use PHPStan\Type\ObjectWithoutClassType;
use PHPStan\Type\StaticType;
use PHPStan\Type\Type as TypeInterface;
use TYPO3\CMS\Extbase\Object\ObjectManagerInterface;

class ObjectManagerInterfaceGetDynamicReturnTypeExtension implements DynamicMethodReturnTypeExtension
{
    public function getClass(): string
    {
        return ObjectManagerInterface::class;
    }

    public function isMethodSupported(MethodReflection $methodReflection): bool
    {
        return $methodReflection->getName() === 'get';
    }

    public function getTypeFromMethodCall(MethodReflection $methodReflection, MethodCall $methodCall, Scope $scope): TypeInterface
    {
        try {
            $classNameArgument = $this->fetchClassNameArgument($methodCall);
            $classNameArgumentValueExpression = $classNameArgument->value;

            switch (true) {
                case $classNameArgumentValueExpression instanceof StringNode:
                    /*
                     * Examples:
                     *
                     * - GeneralUtility::makeInstance('foo')
                     * - GeneralUtility::makeInstance('TYPO3\\CMS\\Core\\DataHandling\\DataHandler')
                     */
                    return $this->createObjectTypeFromString($classNameArgumentValueExpression);
                case $classNameArgumentValueExpression instanceof ClassConstFetch:
                    /*
                     * Examples:
                     *
                     * - GeneralUtility::makeInstance(TYPO3\CMS\Core\DataHandling\DataHandler::class)
                     * - GeneralUtility::makeInstance(self::class)
                     * - GeneralUtility::makeInstance(static::class)
                     */
                    return $this->createObjectTypeFromClassConstFetch($classNameArgumentValueExpression, $scope->getClassReflection());
                default:
                    throw new \InvalidArgumentException(
                        'Argument $className is neither a string nor a class constant',
                        1584879239
                    );
            }
        } catch (\Throwable $exception) {
            return new ObjectWithoutClassType();
        }
    }

    private function fetchClassNameArgument(MethodCall $methodCall): ArgumentNode
    {
        if (empty($methodCall->args)) {
            /*
             * This usually does not happen as calling GeneralUtility::makeInstance() without the mandatory argument
             * $className results in a syntax error.
             */
            throw new \LogicException('Method makeInstance is called without arguments.', 1584881724);
        }

        return $methodCall->args[0];
    }

    private function createObjectTypeFromString(StringNode $string): TypeInterface
    {
        $className = $string->value;

        if (!class_exists($className)) {
            throw new \LogicException('makeInstance has been called with non class name string', 1584881731);
        }

        return new ObjectType($className);
    }

    private function createObjectTypeFromClassConstFetch(ClassConstFetch $expression, ?ClassReflection $classReflection): TypeInterface
    {
        $class = $expression->class;
        if (!$class instanceof Name) {
            throw new \LogicException('', 1584881734);
        }
        /** @var Name $class */

        $className = $class->toString();

        if ($className === 'self' && $classReflection !== null) {
            return new ObjectType($classReflection->getName());
        }

        if ($className === 'static' && $classReflection !== null) {
            $callingClass = $classReflection->getName();
            return new StaticType($callingClass);
        }

        return new ObjectType($className);
    }
}
