<?php

namespace App\Services;

use ReflectionClass;
use ReflectionMethod;

/**
 * Service pour lire et parser les annotations/commentaires de documentation
 * 
 * Extrait les informations des commentaires PHPDoc et des attributs PHP 8
 * pour enrichir les métadonnées des fonctionnalités API.
 */
class AnnotationReader
{
    /**
     * Extrait les annotations d'une méthode de contrôleur
     * 
     * @param string $className
     * @param string $methodName
     * @return array
     */
    public function extractMethodAnnotations(string $className, string $methodName): array
    {
        try {
            $reflection = new ReflectionClass($className);
            $method = $reflection->getMethod($methodName);
            
            $annotations = [
                'title' => null,
                'description' => null,
                'permissions' => [],
                'parameters' => [],
                'responses' => [],
                'mobile_compatible' => true,
                'ui_type' => 'generic',
                'form_schema' => null,
                'list_schema' => null,
            ];
            
            // Parser le commentaire PHPDoc
            $docComment = $method->getDocComment();
            if ($docComment) {
                $annotations = array_merge($annotations, $this->parseDocComment($docComment));
            }
            
            // Parser les attributs PHP 8
            $attributes = $this->parseMethodAttributes($method);
            $annotations = array_merge($annotations, $attributes);
            
            return $annotations;
        } catch (\ReflectionException) {
            return [];
        }
    }

    /**
     * Parse un commentaire PHPDoc pour extraire les informations
     * 
     * @param string $docComment
     * @return array
     */
    private function parseDocComment(string $docComment): array
    {
        $annotations = [];
        
        // Nettoyer le commentaire
        $comment = preg_replace('/^\s*\/\*\*|\*\/\s*$/', '', $docComment);
        $lines = array_map(fn($line) => trim(ltrim($line, ' *')), explode("\n", $comment));
        
        $description = [];
        $currentTag = null;
        
        foreach ($lines as $line) {
            if (empty($line)) {
                continue;
            }
            
            // Détecter les tags @
            if (preg_match('/^@(\w+)(.*)/', $line, $matches)) {
                $currentTag = $matches[1];
                $value = trim($matches[2]);
                
                switch ($currentTag) {
                    case 'title':
                        $annotations['title'] = $value;
                        break;
                    case 'description':
                        $annotations['description'] = $value;
                        break;
                    case 'permission':
                        $annotations['permissions'][] = $value;
                        break;
                    case 'mobile':
                        $annotations['mobile_compatible'] = $value !== 'false';
                        break;
                    case 'ui':
                        $annotations['ui_type'] = $value;
                        break;
                    case 'param':
                        $annotations['parameters'][] = $this->parseParamTag($value);
                        break;
                    case 'response':
                        $annotations['responses'][] = $this->parseResponseTag($value);
                        break;
                }
            } elseif ($currentTag === null && !empty($line)) {
                // Description principale
                $description[] = $line;
            }
        }
        
        // Si pas de description explicite, utiliser les premières lignes
        if (empty($annotations['description']) && !empty($description)) {
            $annotations['description'] = implode(' ', $description);
        }
        
        return $annotations;
    }

    /**
     * Parse les attributs PHP 8 d'une méthode
     * 
     * @param ReflectionMethod $method
     * @return array
     */
    private function parseMethodAttributes(ReflectionMethod $method): array
    {
        $annotations = [];
        
        foreach ($method->getAttributes() as $attribute) {
            $name = $attribute->getName();
            $arguments = $attribute->getArguments();
            
            // Mapper les attributs connus
            switch ($name) {
                case 'App\\Attributes\\ApiFeature':
                    $annotations['title'] = $arguments['title'] ?? null;
                    $annotations['description'] = $arguments['description'] ?? null;
                    $annotations['ui_type'] = $arguments['ui_type'] ?? 'generic';
                    $annotations['mobile_compatible'] = $arguments['mobile_compatible'] ?? true;
                    $annotations['mobile_version_min'] = $arguments['mobile_version_min'] ?? null;
                    $annotations['mobile_version_max'] = $arguments['mobile_version_max'] ?? null;
                    $annotations['form_schema'] = $arguments['form_schema'] ?? [];
                    $annotations['list_schema'] = $arguments['list_schema'] ?? [];
                    break;
                    
                case 'App\\Attributes\\RequiresPermission':
                    $permissions = $arguments['permissions'] ?? $arguments[0] ?? [];
                    if (!is_array($permissions)) {
                        $permissions = [$permissions];
                    }
                    $annotations['permissions'] = array_merge(
                        $annotations['permissions'] ?? [],
                        $permissions
                    );
                    break;
                    
                case 'App\\Attributes\\MobileCompatible':
                    $annotations['mobile_compatible'] = $arguments['compatible'] ?? $arguments[0] ?? true;
                    $annotations['mobile_version_min'] = $arguments['minimum_version'] ?? $annotations['mobile_version_min'] ?? null;
                    $annotations['mobile_version_max'] = $arguments['maximum_version'] ?? $annotations['mobile_version_max'] ?? null;
                    break;
            }
        }
        
        return $annotations;
    }

    /**
     * Parse un tag @param
     * 
     * @param string $value
     * @return array
     */
    private function parseParamTag(string $value): array
    {
        // Format: @param type $name description
        if (preg_match('/^(\S+)\s+\$(\w+)\s*(.*)/', $value, $matches)) {
            return [
                'type' => $matches[1],
                'name' => $matches[2],
                'description' => $matches[3] ?? '',
                'required' => !str_contains($matches[1], '?') && !str_contains($matches[3], 'optional'),
            ];
        }
        
        return ['raw' => $value];
    }

    /**
     * Parse un tag @response
     * 
     * @param string $value
     * @return array
     */
    private function parseResponseTag(string $value): array
    {
        // Format: @response code description
        if (preg_match('/^(\d+)\s+(.*)/', $value, $matches)) {
            return [
                'status_code' => (int) $matches[1],
                'description' => $matches[2],
            ];
        }
        
        return ['raw' => $value];
    }

    /**
     * Génère un titre automatique basé sur le nom de la méthode
     * 
     * @param string $methodName
     * @return string
     */
    public function generateTitleFromMethod(string $methodName): string
    {
        $titles = [
            'index' => 'Liste des éléments',
            'show' => 'Afficher un élément',
            'store' => 'Créer un nouvel élément',
            'update' => 'Modifier un élément',
            'destroy' => 'Supprimer un élément',
            'create' => 'Formulaire de création',
            'edit' => 'Formulaire de modification',
        ];
        
        if (isset($titles[$methodName])) {
            return $titles[$methodName];
        }
        
        // Convertir camelCase en titre lisible
        $title = preg_replace('/([A-Z])/', ' $1', $methodName);
        $title = ucfirst(trim($title));
        
        return $title;
    }

    /**
     * Génère une description automatique basée sur le nom de la méthode
     * 
     * @param string $methodName
     * @param string $controllerName
     * @return string
     */
    public function generateDescriptionFromMethod(string $methodName, string $controllerName): string
    {
        $resource = $this->extractResourceName($controllerName);
        
        $descriptions = [
            'index' => "Récupère la liste des {$resource}",
            'show' => "Affiche les détails d'un {$resource}",
            'store' => "Crée un nouveau {$resource}",
            'update' => "Met à jour un {$resource} existant",
            'destroy' => "Supprime un {$resource}",
        ];
        
        if (isset($descriptions[$methodName])) {
            return $descriptions[$methodName];
        }
        
        return "Exécute l'action {$methodName} sur {$resource}";
    }

    /**
     * Extrait le nom de la ressource depuis le nom du contrôleur
     * 
     * @param string $controllerName
     * @return string
     */
    private function extractResourceName(string $controllerName): string
    {
        // Extraire le nom court du contrôleur
        $shortName = class_basename($controllerName);
        
        // Supprimer "Controller" à la fin
        $resource = str_replace('Controller', '', $shortName);
        
        // Convertir en minuscules et ajouter des espaces
        $resource = strtolower(preg_replace('/([A-Z])/', ' $1', $resource));
        
        return trim($resource);
    }
}