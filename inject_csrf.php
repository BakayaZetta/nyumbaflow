<?php
// Script to inject CSRF tokens into all forms
$dir = __DIR__;
$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir));

$count = 0;
foreach ($iterator as $file) {
    if ($file->isFile() && $file->getExtension() === 'php') {
        $path = $file->getRealPath();
        
        // Skip specific files
        $basename = basename($path);
        if (in_array($basename, ['csrf_token.php', 'scsrf.php', 'auth_action.php', 'onboarding_action.php', 'inject_csrf.php', 'auth.php', 'onboarding.php'])) {
            continue;
        }

        $content = file_get_contents($path);
        
        // Only replace if it contains a form and doesn't already have the token field right after it
        if (stripos($content, '<form') !== false) {
            // First, remove the bad injection I just did in access_control.php
            $content = str_replace("`n<?php echo get_csrf_token_field(); ?>", '', $content);
            $content = str_replace("\n<?php echo get_csrf_token_field(); ?>", '', $content);
            
            // Re-inject properly
            $newContent = preg_replace('/(<form[^>]*>)/i', "$1\n<?php echo get_csrf_token_field(); ?>", $content);
            
            if ($newContent !== $content) {
                file_put_contents($path, $newContent);
                $count++;
                echo "Updated: $basename\n";
            }
        }
    }
}
echo "Total files updated: $count\n";
?>
