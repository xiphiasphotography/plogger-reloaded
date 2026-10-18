<?php
/** Static approximation only: templates are tokenized, never executed. */
/** @return array<array-key, mixed> */
function plogger_check_theme(string $directory): array {
    if(!function_exists('token_get_all')) throw new RuntimeException('Enable the PHP tokenizer extension for safe theme inspection.');
    $errors=[]; $warnings=[]; $calls=[]; $known=array_fill_keys(get_defined_functions()['internal'],true);
    $files=[];
    $iterator=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory,FilesystemIterator::SKIP_DOTS));
    foreach($iterator as $file) {
        if($file->isFile() && !$file->isLink() && strtolower($file->getExtension())==='php') $files[]=$file->getPathname();
        if(count($files)>500) throw new RuntimeException('Theme exceeds static scan file limit.');
    }
    sort($files,SORT_STRING);
    $sources=array_merge([__DIR__.'/plog-functions.php',__DIR__.'/plog-extensions.php',dirname(__DIR__).'/plogger.php'], $files);
    $bytes=0; $fingerprint=hash_init('sha256');
    foreach($sources as $file) {
        $bytes+=filesize($file);
        if($bytes>8388608 || filesize($file)>1048576) throw new RuntimeException('Theme exceeds static scan byte limit.');
        $contents=file_get_contents($file); hash_update($fingerprint,basename($file).$contents);
        try { $tokens=token_get_all($contents,TOKEN_PARSE); }
        catch(ParseError $error) { $errors[]=basename($file).': PHP syntax error at line '.$error->getLine(); continue; }
        $tokens=array_values(array_filter($tokens,static fn($token)=>!is_array($token) || !in_array($token[0],[T_COMMENT,T_DOC_COMMENT,T_WHITESPACE],true)));
        foreach($tokens as $index=>$token) {
            if(!is_array($token) || $token[0]!==T_STRING) continue;
            $previous=$tokens[$index-1] ?? null;
            if(is_array($previous) && $previous[0]===T_FUNCTION) $known[strtolower($token[1])]=true;
            elseif(($tokens[$index+1] ?? null)==='(' && (!is_array($previous)
                || !in_array($previous[0],[T_OBJECT_OPERATOR,T_NULLSAFE_OBJECT_OPERATOR,T_DOUBLE_COLON,T_NEW],true))) {
                if(in_array($file,$files,true)) $calls[strtolower($token[1])]=basename($file).':'.$token[2];
            }
        }
    }
    foreach($calls as $function=>$location) if(!isset($known[$function])) $warnings[]=$location.': possible missing function '.$function.' (may be supplied by an include/plugin).';
    return ['files'=>count($files),'errors'=>$errors,'warnings'=>$warnings,'source_hash'=>hash_final($fingerprint),'limitations'=>'Static approximation; runtime, included plugin code and browser behavior require staging checks.'];
}
