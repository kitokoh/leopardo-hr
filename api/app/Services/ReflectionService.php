<?php

namespace App\Services;

use ReflectionClass;
use ReflectionException;
use ReflectionMethod;

/**
 * Service pour l'analyse par reflection des classes et méthodes PHP
 *
 * Fournit des méthodes utilitaires pour analyser les contrôleurs API
 * et extraire leurs informations structurelles.
 */
class ReflectionService
{
    /**
     * Analyse une classe et retourne ses informations
     *
     * @param  string  $className  Nom complet de la classe
     * @return array Informations sur la classe
     *
     * @throws ReflectionException
     */
    public function analyzeClass(string $className): array
    {
        try {
            $reflection = new ReflectionClass($className);

            return [
                'name' => $reflection->getName(),
                'short_name' => $reflection->getShortName(),
                'namespace' => $reflection->getNamespaceName(),
                'filename' => $reflection->getFileName(),
                'methods' => $this->getPublicMethods($reflection),
                'attributes' => $this->getClassAttributes($reflection),
                'doc_comment' => $reflection->getDocComment() ?: null,
            ];
        } catch (ReflectionException $e) {
            throw new ReflectionException("Cannot analyze class {$className}: ".$e->getMessage());
        }
    }

    /**
     * Analyse une méthode spécifique d'une classe
     *
     * @param  string  $className  Nom complet de la classe
     * @param  string  $methodName  Nom de la méthode
     * @return array Informations sur la méthode
     *
     * @throws ReflectionException
     */
    public function analyzeMethod(string $className, string $methodName): array
    {
        try {
            $reflection = new ReflectionClass($className);
            $method = $reflection->getMethod($methodName);

            return [
                'name' => $method->getName(),
                'class' => $method->getDeclaringClass()->getName(),
                'visibility' => $this->getMethodVisibility($method),
                'parameters' => $this->getMethodParameters($method),
                'return_type' => ($returnType = $method->getReturnType()) instanceof \ReflectionNamedType ? $returnType->getName() : null,
                'attributes' => $this->getMethodAttributes($method),
                'doc_comment' => $method->getDocComment() ?: null,
                'signature' => $this->generateMethodSignature($method),
            ];
        } catch (ReflectionException $e) {
            throw new ReflectionException("Cannot analyze method {$className}::{$methodName}: ".$e->getMessage());
        }
    }

    /**
     * Extrait les paramètres d'une méthode
     */
    private function getMethodParameters(ReflectionMethod $method): array
    {
        $parameters = [];

        foreach ($method->getParameters() as $parameter) {
            $parameters[] = [
                'name' => $parameter->getName(),
                'type' => ($type = $parameter->getType()) instanceof \ReflectionNamedType ? $type->getName() : null,
                'has_default' => $parameter->isDefaultValueAvailable(),
                'default_value' => $parameter->isDefaultValueAvailable()
                    ? $parameter->getDefaultValue()
                    : null,
                'is_optional' => $parameter->isOptional(),
                'allows_null' => $parameter->allowsNull(),
            ];
        }

        return $parameters;
    }

    /**
     * Obtient les méthodes publiques d'une classe
     */
    private function getPublicMethods(ReflectionClass $reflection): array
    {
        $methods = [];

        foreach ($reflection->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            // Ignorer les méthodes héritées de classes de base Laravel
            if ($method->getDeclaringClass()->getName() !== $reflection->getName()) {
                continue;
            }

            // Ignorer les méthodes magiques et constructeur
            if (str_starts_with($method->getName(), '__')) {
                continue;
            }

            $methods[] = $method->getName();
        }

        return $methods;
    }

    /**
     * Extrait les attributs PHP 8 d'une classe
     */
    private function getClassAttributes(ReflectionClass $reflection): array
    {
        $attributes = [];

        foreach ($reflection->getAttributes() as $attribute) {
            $attributes[] = [
                'name' => $attribute->getName(),
                'arguments' => $attribute->getArguments(),
            ];
        }

        return $attributes;
    }

    /**
     * Extrait les attributs PHP 8 d'une méthode
     */
    private function getMethodAttributes(ReflectionMethod $method): array
    {
        $attributes = [];

        foreach ($method->getAttributes() as $attribute) {
            $attributes[] = [
                'name' => $attribute->getName(),
                'arguments' => $attribute->getArguments(),
            ];
        }

        return $attributes;
    }

    /**
     * Détermine la visibilité d'une méthode
     */
    private function getMethodVisibility(ReflectionMethod $method): string
    {
        if ($method->isPublic()) {
            return 'public';
        }

        if ($method->isProtected()) {
            return 'protected';
        }

        return 'private';
    }

    /**
     * Génère une signature unique pour une méthode
     */
    private function generateMethodSignature(ReflectionMethod $method): string
    {
        $parameters = [];

        foreach ($method->getParameters() as $parameter) {
            $param = '';

            if (($type = $parameter->getType()) instanceof \ReflectionNamedType) {
                $param .= $type->getName().' ';
            }

            $param .= '$'.$parameter->getName();

            if ($parameter->isDefaultValueAvailable()) {
                $param .= ' = '.var_export($parameter->getDefaultValue(), true);
            }

            $parameters[] = $param;
        }

        $signature = $method->getName().'('.implode(', ', $parameters).')';

        if (($returnType = $method->getReturnType()) instanceof \ReflectionNamedType) {
            $signature .= ': '.$returnType->getName();
        }

        return $signature;
    }

    /**
     * Vérifie si une classe est un contrôleur API
     */
    public function isApiController(string $className): bool
    {
        try {
            $reflection = new ReflectionClass($className);

            // Vérifier si c'est dans le namespace des contrôleurs API
            $namespace = $reflection->getNamespaceName();
            if (! str_contains($namespace, 'App\\Http\\Controllers\\Api')) {
                return false;
            }

            // Vérifier si c'est une sous-classe de Controller
            $parentClass = $reflection->getParentClass();
            while ($parentClass) {
                if ($parentClass->getName() === 'App\\Http\\Controllers\\Controller') {
                    return true;
                }
                $parentClass = $parentClass->getParentClass();
            }

            return false;
        } catch (ReflectionException) {
            return false;
        }
    }
}
