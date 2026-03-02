<?php

namespace Tools\Audit\Analyzers;

use Tools\Audit\Interfaces\AnalyzerInterface;

/**
 * Analyzer for NextAuth.js authentication configuration.
 * 
 * Parses NextAuth.js configuration to extract session structure,
 * storage mechanism, LINE account filtering logic, and role-based
 * access control implementation.
 * 
 * Requirements: 3.1, 3.3, 3.4
 */
class NextAuthAnalyzer implements AnalyzerInterface
{
    private string $basePath;
    private array $validationErrors = [];
    private array $authConfig = [];

    /**
     * Constructor.
     * 
     * @param string $basePath Base path to Next.js codebase
     */
    public function __construct(string $basePath)
    {
        $this->basePath = rtrim($basePath, '/');
    }

    /**
     * Run the analysis and return results.
     * 
     * @return array Analysis results with authentication configuration
     */
    public function analyze(): array
    {
        if (!$this->validate()) {
            return [
                'success' => false,
                'errors' => $this->validationErrors,
                'authConfig' => [],
            ];
        }

        $this->authConfig = [
            'provider' => 'NextAuth.js',
            'version' => $this->detectNextAuthVersion(),
            'sessionStorage' => $this->extractSessionStorage(),
            'sessionStructure' => $this->extractSessionStructure(),
            'sessionStrategy' => $this->extractSessionStrategy(),
            'tokenValidation' => $this->extractTokenValidation(),
            'lineAccountFiltering' => $this->extractLineAccountFiltering(),
            'roleBasedAccess' => $this->extractRoleBasedAccess(),
            'authProviders' => $this->extractAuthProviders(),
            'sessionMaxAge' => $this->extractSessionMaxAge(),
            'callbacks' => $this->extractCallbacks(),
            'pages' => $this->extractCustomPages(),
        ];

        return [
            'success' => true,
            'authConfig' => $this->authConfig,
        ];
    }

    /**
     * Get the name of this analyzer.
     * 
     * @return string Analyzer name
     */
    public function getName(): string
    {
        return 'NextAuthAnalyzer';
    }

    /**
     * Get the version of this analyzer.
     * 
     * @return string Version string
     */
    public function getVersion(): string
    {
        return '1.0.0';
    }

    /**
     * Validate that the analyzer is ready to run.
     * 
     * @return bool True if ready
     */
    public function validate(): bool
    {
        $this->validationErrors = [];

        if (empty($this->basePath)) {
            $this->validationErrors[] = 'Base path is not set';
        }

        if (!is_dir($this->basePath)) {
            $this->validationErrors[] = "Base path does not exist: {$this->basePath}";
        }

        // Check for auth configuration file
        $authPaths = [
            $this->basePath . '/src/lib/auth.ts',
            $this->basePath . '/src/auth.ts',
            $this->basePath . '/lib/auth.ts',
            $this->basePath . '/auth.ts',
        ];

        $authFileFound = false;
        foreach ($authPaths as $path) {
            if (file_exists($path)) {
                $authFileFound = true;
                break;
            }
        }

        if (!$authFileFound) {
            $this->validationErrors[] = 'NextAuth configuration file not found in expected locations';
        }

        return empty($this->validationErrors);
    }

    /**
     * Get validation errors.
     * 
     * @return array Array of error messages
     */
    public function getValidationErrors(): array
    {
        return $this->validationErrors;
    }

    /**
     * Detect NextAuth.js version from package.json or imports.
     * 
     * @return string Version string or 'unknown'
     */
    private function detectNextAuthVersion(): string
    {
        $packageJsonPath = $this->basePath . '/package.json';
        
        if (file_exists($packageJsonPath)) {
            $packageJson = json_decode(file_get_contents($packageJsonPath), true);
            
            if (isset($packageJson['dependencies']['next-auth'])) {
                return $packageJson['dependencies']['next-auth'];
            }
        }

        // Try to detect from import style
        $authFile = $this->findAuthFile();
        if ($authFile) {
            $content = file_get_contents($authFile);
            
            // NextAuth v5 uses: import NextAuth from "next-auth"
            // NextAuth v4 uses: import NextAuth from "next-auth/next"
            if (preg_match('/import\s+NextAuth\s+from\s+[\'"]next-auth[\'"]/', $content)) {
                return '5.x (detected from imports)';
            } elseif (preg_match('/import\s+NextAuth\s+from\s+[\'"]next-auth\/next[\'"]/', $content)) {
                return '4.x (detected from imports)';
            }
        }

        return 'unknown';
    }

    /**
     * Extract session storage mechanism.
     * 
     * @return array Session storage details
     */
    private function extractSessionStorage(): array
    {
        $authFile = $this->findAuthFile();
        if (!$authFile) {
            return ['type' => 'unknown', 'details' => []];
        }

        $content = file_get_contents($authFile);

        // Check session strategy
        if (preg_match('/strategy:\s*[\'"]jwt[\'"]/', $content)) {
            return [
                'type' => 'JWT',
                'details' => [
                    'storage' => 'HTTP-only cookie',
                    'server_side' => false,
                    'description' => 'JWT tokens stored in HTTP-only cookies',
                ],
            ];
        } elseif (preg_match('/strategy:\s*[\'"]database[\'"]/', $content)) {
            return [
                'type' => 'Database',
                'details' => [
                    'storage' => 'Database sessions table',
                    'server_side' => true,
                    'adapter' => $this->detectDatabaseAdapter($content),
                    'description' => 'Sessions stored in database via Prisma adapter',
                ],
            ];
        }

        // Default to JWT if not specified (NextAuth v5 default)
        return [
            'type' => 'JWT (default)',
            'details' => [
                'storage' => 'HTTP-only cookie',
                'server_side' => false,
                'description' => 'JWT tokens stored in HTTP-only cookies (default strategy)',
            ],
        ];
    }

    /**
     * Detect database adapter from auth configuration.
     * 
     * @param string $content Auth file content
     * @return string Adapter name or 'none'
     */
    private function detectDatabaseAdapter(string $content): string
    {
        if (preg_match('/PrismaAdapter\s*\(/', $content)) {
            return 'PrismaAdapter';
        }
        
        if (preg_match('/adapter:\s*(\w+Adapter)/', $content, $match)) {
            return $match[1];
        }

        return 'none';
    }

    /**
     * Extract session structure from JWT callbacks and type definitions.
     * 
     * @return array Session structure
     */
    private function extractSessionStructure(): array
    {
        $authFile = $this->findAuthFile();
        if (!$authFile) {
            return [];
        }

        $content = file_get_contents($authFile);
        $structure = [
            'user' => [],
            'token' => [],
            'expires' => 'ISO 8601 datetime string',
        ];

        // Extract from session callback
        if (preg_match('/async\s+session\s*\(\s*\{[^}]*session[^}]*\}\s*\)\s*\{([^}]+)\}/s', $content, $match)) {
            $sessionCallback = $match[1];
            
            // Extract session.user fields
            preg_match_all('/session\.user\.(\w+)\s*=/', $sessionCallback, $userFields);
            if (!empty($userFields[1])) {
                foreach ($userFields[1] as $field) {
                    $structure['user'][$field] = 'extracted from token';
                }
            }
        }

        // Extract from JWT callback
        if (preg_match('/async\s+jwt\s*\(\s*\{[^}]*token[^}]*\}\s*\)\s*\{([^}]+)\}/s', $content, $match)) {
            $jwtCallback = $match[1];
            
            // Extract token fields
            preg_match_all('/token\.(\w+)\s*=/', $jwtCallback, $tokenFields);
            if (!empty($tokenFields[1])) {
                foreach ($tokenFields[1] as $field) {
                    $structure['token'][$field] = 'stored in JWT';
                }
            }
        }

        // Extract from type augmentation
        if (preg_match('/interface\s+Session\s*\{[^}]*user:\s*\{([^}]+)\}/s', $content, $match)) {
            $userInterface = $match[1];
            
            preg_match_all('/(\w+)\??:\s*([^;\n]+)/', $userInterface, $typeMatches, PREG_SET_ORDER);
            foreach ($typeMatches as $typeMatch) {
                $fieldName = trim($typeMatch[1]);
                $fieldType = trim($typeMatch[2]);
                $structure['user'][$fieldName] = $fieldType;
            }
        }

        return $structure;
    }

    /**
     * Extract session strategy (JWT or database).
     * 
     * @return string Session strategy
     */
    private function extractSessionStrategy(): string
    {
        $authFile = $this->findAuthFile();
        if (!$authFile) {
            return 'unknown';
        }

        $content = file_get_contents($authFile);

        if (preg_match('/strategy:\s*[\'"](\w+)[\'"]/', $content, $match)) {
            return $match[1];
        }

        return 'jwt (default)';
    }

    /**
     * Extract token validation mechanism.
     * 
     * @return array Token validation details
     */
    private function extractTokenValidation(): array
    {
        $authFile = $this->findAuthFile();
        if (!$authFile) {
            return ['method' => 'unknown', 'details' => []];
        }

        $content = file_get_contents($authFile);

        $validation = [
            'method' => 'NextAuth.js built-in',
            'secret' => $this->extractAuthSecret($content),
            'trustHost' => $this->extractTrustHost($content),
            'details' => [
                'jwt_verification' => 'Automatic via NextAuth.js',
                'signature_algorithm' => 'HS256 (default)',
                'token_rotation' => 'Automatic on session update',
            ],
        ];

        return $validation;
    }

    /**
     * Extract auth secret configuration.
     * 
     * @param string $content Auth file content
     * @return string Secret configuration
     */
    private function extractAuthSecret(string $content): string
    {
        if (preg_match('/secret:\s*([^,\n]+)/', $content, $match)) {
            $secretExpr = trim($match[1]);
            
            // Extract environment variable names
            if (preg_match_all('/process\.env\.(\w+)/', $secretExpr, $envMatches)) {
                return 'Environment variables: ' . implode(', ', $envMatches[1]);
            }
            
            return $secretExpr;
        }

        return 'Not explicitly configured (uses default)';
    }

    /**
     * Extract trustHost configuration.
     * 
     * @param string $content Auth file content
     * @return bool Trust host setting
     */
    private function extractTrustHost(string $content): bool
    {
        if (preg_match('/trustHost:\s*(true|false)/', $content, $match)) {
            return $match[1] === 'true';
        }

        return false;
    }

    /**
     * Extract LINE account filtering logic.
     * 
     * @return array LINE account filtering details
     */
    private function extractLineAccountFiltering(): array
    {
        $authFile = $this->findAuthFile();
        if (!$authFile) {
            return ['enabled' => false, 'details' => []];
        }

        $content = file_get_contents($authFile);

        $filtering = [
            'enabled' => false,
            'field' => null,
            'storage' => [],
            'usage' => [],
        ];

        // Check if lineAccountId is in session structure
        if (preg_match('/lineAccountId[\'"]?\s*[:=]/', $content)) {
            $filtering['enabled'] = true;
            $filtering['field'] = 'lineAccountId';
        }

        // Check JWT callback
        if (preg_match('/token\.lineAccountId\s*=\s*user\.lineAccountId/', $content)) {
            $filtering['storage'][] = 'Stored in JWT token';
        }

        // Check session callback
        if (preg_match('/session\.user\.lineAccountId\s*=\s*token\.lineAccountId/', $content)) {
            $filtering['storage'][] = 'Exposed in session object';
        }

        // Check authorize callback
        if (preg_match('/lineAccountId:\s*adminUser\.lineAccountId/', $content)) {
            $filtering['storage'][] = 'Extracted from adminUser during authorization';
        }

        // Search for usage in API routes
        $apiPath = $this->basePath . '/src/app/api';
        if (is_dir($apiPath)) {
            $filtering['usage'] = $this->searchLineAccountUsage($apiPath);
        }

        return $filtering;
    }

    /**
     * Search for LINE account ID usage in API routes.
     * 
     * @param string $dir Directory to search
     * @return array Usage examples
     */
    private function searchLineAccountUsage(string $dir): array
    {
        $usage = [];
        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \RecursiveDirectoryIterator::SKIP_DOTS)
        );

        $count = 0;
        foreach ($files as $file) {
            if ($file->isFile() && preg_match('/\.(ts|tsx|js|jsx)$/', $file->getFilename())) {
                $content = file_get_contents($file->getPathname());
                
                if (preg_match('/session\.user\.lineAccountId/', $content)) {
                    $relativePath = str_replace($this->basePath . '/', '', $file->getPathname());
                    $usage[] = $relativePath;
                    $count++;
                    
                    // Limit to 10 examples
                    if ($count >= 10) {
                        break;
                    }
                }
            }
        }

        return $usage;
    }

    /**
     * Extract role-based access control implementation.
     * 
     * @return array RBAC details
     */
    private function extractRoleBasedAccess(): array
    {
        $authFile = $this->findAuthFile();
        if (!$authFile) {
            return ['enabled' => false, 'details' => []];
        }

        $content = file_get_contents($authFile);

        $rbac = [
            'enabled' => false,
            'field' => null,
            'roles' => [],
            'storage' => [],
            'middleware' => [],
        ];

        // Check if role is in session structure
        if (preg_match('/role[\'"]?\s*[:=]/', $content)) {
            $rbac['enabled'] = true;
            $rbac['field'] = 'role';
        }

        // Extract role from adminUser
        if (preg_match('/role:\s*adminUser\.role/', $content)) {
            $rbac['storage'][] = 'Extracted from adminUser.role during authorization';
        }

        // Check JWT callback
        if (preg_match('/token\.role\s*=\s*user\.role/', $content)) {
            $rbac['storage'][] = 'Stored in JWT token';
        }

        // Check session callback
        if (preg_match('/session\.user\.role\s*=\s*token\.role/', $content)) {
            $rbac['storage'][] = 'Exposed in session object';
        }

        // Search for middleware usage
        $middlewarePath = $this->basePath . '/src/middleware.ts';
        if (file_exists($middlewarePath)) {
            $middlewareContent = file_get_contents($middlewarePath);
            if (preg_match('/session\.user\.role/', $middlewareContent)) {
                $rbac['middleware'][] = 'Role-based routing in middleware.ts';
            }
        }

        return $rbac;
    }

    /**
     * Extract authentication providers.
     * 
     * @return array List of providers
     */
    private function extractAuthProviders(): array
    {
        $authFile = $this->findAuthFile();
        if (!$authFile) {
            return [];
        }

        $content = file_get_contents($authFile);
        $providers = [];

        // Extract provider imports
        preg_match_all('/import\s+(\w+)\s+from\s+[\'"]next-auth\/providers\/(\w+)[\'"]/', $content, $matches, PREG_SET_ORDER);
        
        foreach ($matches as $match) {
            $providerName = $match[2];
            $providers[] = [
                'name' => $providerName,
                'type' => $this->getProviderType($providerName),
            ];
        }

        return $providers;
    }

    /**
     * Get provider type from provider name.
     * 
     * @param string $name Provider name
     * @return string Provider type
     */
    private function getProviderType(string $name): string
    {
        $oauthProviders = ['google', 'github', 'facebook', 'twitter', 'discord', 'apple'];
        
        if (in_array(strtolower($name), $oauthProviders)) {
            return 'OAuth';
        }
        
        if (strtolower($name) === 'credentials') {
            return 'Credentials';
        }
        
        if (strtolower($name) === 'email') {
            return 'Email (Magic Link)';
        }

        return 'Unknown';
    }

    /**
     * Extract session max age.
     * 
     * @return int|null Max age in seconds or null
     */
    private function extractSessionMaxAge(): ?int
    {
        $authFile = $this->findAuthFile();
        if (!$authFile) {
            return null;
        }

        $content = file_get_contents($authFile);

        if (preg_match('/maxAge:\s*(\d+(?:\s*\*\s*\d+)*(?:\s*\*\s*\d+)*(?:\s*\*\s*\d+)*)/', $content, $match)) {
            $expression = $match[1];
            
            // Evaluate simple multiplication expressions
            $expression = preg_replace('/\s+/', '', $expression);
            $parts = explode('*', $expression);
            $result = 1;
            foreach ($parts as $part) {
                $result *= intval($part);
            }
            
            return $result;
        }

        // Default NextAuth session maxAge is 30 days
        return 30 * 24 * 60 * 60;
    }

    /**
     * Extract callbacks configuration.
     * 
     * @return array Callbacks details
     */
    private function extractCallbacks(): array
    {
        $authFile = $this->findAuthFile();
        if (!$authFile) {
            return [];
        }

        $content = file_get_contents($authFile);
        $callbacks = [];

        // Check for each callback type
        $callbackTypes = ['jwt', 'session', 'signIn', 'redirect', 'authorized'];
        
        foreach ($callbackTypes as $type) {
            if (preg_match('/async\s+' . $type . '\s*\(/', $content)) {
                $callbacks[$type] = 'Implemented';
            }
        }

        return $callbacks;
    }

    /**
     * Extract custom pages configuration.
     * 
     * @return array Custom pages
     */
    private function extractCustomPages(): array
    {
        $authFile = $this->findAuthFile();
        if (!$authFile) {
            return [];
        }

        $content = file_get_contents($authFile);
        $pages = [];

        // Extract pages configuration
        if (preg_match('/pages:\s*\{([^}]+)\}/', $content, $match)) {
            $pagesConfig = $match[1];
            
            preg_match_all('/(\w+):\s*[\'"]([^\'"]+)[\'"]/', $pagesConfig, $pageMatches, PREG_SET_ORDER);
            
            foreach ($pageMatches as $pageMatch) {
                $pages[$pageMatch[1]] = $pageMatch[2];
            }
        }

        return $pages;
    }

    /**
     * Find the auth configuration file.
     * 
     * @return string|null Path to auth file or null
     */
    private function findAuthFile(): ?string
    {
        $authPaths = [
            $this->basePath . '/src/lib/auth.ts',
            $this->basePath . '/src/auth.ts',
            $this->basePath . '/lib/auth.ts',
            $this->basePath . '/auth.ts',
        ];

        foreach ($authPaths as $path) {
            if (file_exists($path)) {
                return $path;
            }
        }

        return null;
    }
}
