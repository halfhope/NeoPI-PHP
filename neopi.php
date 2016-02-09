<?php

/**
 * @author Ben Hagen <ben.hagen@neohapsis.com>
 * @author Scott Behrens <scott.behrens@neohapsis.com>
 * @author PHP port Shashakhmetov Talgat <talgatks@gmail.com>
 * @link https://github.com/Neohapsis/NeoPI
 * @link https://github.com/halfhope/NeoPI-PHP/
 */
final class NeoPI
{
    public static $results = array();
    // IC vars
    private static $char_count = array();
    private static $total_char_count = 0;
    private static $ic_total_results = 0;
    public static $tests = array('ic' => array('enabled' => true, 'func' => 'ICCalculate', 'header' => '[[ Top %s lowest IC files. Method time: %s ]]', 'microtime' => 0), 'entropy' => array('enabled' => true, 'func' => 'entropyCalculate', 'header' => '[[ Top %s entropic files for a given search. Method time: %s ]]', 'microtime' => 0), 'longestword' => array('enabled' => true, 'func' => 'longestWordCalculate', 'header' => '[[ Top %s longest word files. Method time: %s ]]', 'microtime' => 0), 'signature' => array('enabled' => true, 'func' => 'signatureNastyCalculate', 'header' => '[[ Top %s signature Nasty match counts. Method time: %s ]]', 'microtime' => 0), 'supersignature' => array('enabled' => true, 'func' => 'signatureSuperNastyCalculate', 'header' => '[[ Top %s SUPER-signature match counts (These are usually bad!). Method time: %s ]]', 'microtime' => 0), 'eval' => array('enabled' => true, 'func' => 'usesEvalCalculate', 'header' => '[[ Top %s eval match counts. Method time: %s ]]', 'microtime' => 0), 'zlib' => array('enabled' => true, 'func' => 'compressionCalculate', 'header' => '[[ Top %s compression match counts. Method time: %s ]]', 'microtime' => 0), 'rank' => array('enabled' => true, 'header' => '[[ Top %s cumulative ranked files ]]', 'microtime' => 0));
    public static $csv = false;
    public static $report_limit = 10;
    
    public static $path = __DIR__;
    public static $extensions = array('php', 'asp', 'aspx', 'sh', 'bash', 'zsh', 'csh', 'tsch', 'pl', 'py', 'txt', 'cgi', 'cfm');
    public static $files = array();
    public static $isCLI;
    private static $opts;
    private static $microtime;
    private static $files_count;
    
    function __construct($auto = true)
    {
        self::$isCLI = (PHP_SAPI === 'cli' || empty($_SERVER['REMOTE_ADDR'])) ? true : false;
        
        $opts = getopt('h::p:e:a::l:c::', array(
            'help::',
            'path:',
            'extensions:',
            'all::',
            'ic::',
            'entropy::',
            'longestword::',
            'signature::',
            'supersignature::',
            'eval::',
            'zlib::',
            'report_limit:',
            'csv::'
        ));
        if (isset($opts['h']) || isset($opts['help'])) {
            die("NeoPI usage:
\t-h, --help <help info> [default=__DIR__]
\t-p, --path <start directory> [default=.]
\t-e, --extensions <file extensions to scan delimiter ;> [default=php]
\t-a, --all <Run all (useful) tests [Entropy, Longest Word, IC, Signature]> [default=false]
\t--ic <Run IC test> [default=true]
\t--entropy <Run entropy Test> [default=true]
\t--longestword <Run longest word test> [default=true]
\t--signature <Run signature test> [default=true]
\t--supersignature <Run SUPER-signature test> [default=true]
\t--eval <Run signiture test for the eval> [default=true]
\t--zlib <Run compression Test> [default=true]
\t-l, --report_limit <Limit files in report lists> [default=10]
\t-c, --csv <Save Result in CSV file> [default=false]" . PHP_EOL);
        } else {
            if (isset($opts['p']) || isset($opts['path'])) {
                self::$path = isset($opts['p']) ? $opts['p'] : $opts['path'];
            }
            
            if (isset($opts['e']) || isset($opts['extensions'])) {
                self::$extensions = explode(';', isset($opts['e']) ? $opts['e'] : $opts['extensions']);
            }
            
            if (isset($opts['c']) || isset($opts['csv'])) {
                self::$csv = true;
            }
            
            if (isset($opts['l']) || isset($opts['report_limit'])) {
                self::$report_limit = (int) isset($opts['l']) ? $opts['l'] : $opts['report_limit'];
            }
            
            if (isset($opts['all'])) {
                foreach (self::$tests as $test_name => $test_data) {
                    self::$tests[$test_name]['enabled'] = true;
                }
            } else {
                foreach ($opts as $opt) {
                    if (isset(self::$tests[$opt]) && !self::$tests[$opt]) {
                        self::$tests[$opt]['enabled'] = true;
                    } else {
                        if (isset(self::$tests[$opt])) {
                            self::$tests[$opt]['enabled'] = false;
                        }
                    }
                }
            }
        }
        
        if (self::$isCLI || $auto) {
            self::getFiles();
            self::runTests();
            self::report();
            exit;
        }
    }
    
    public function runTests()
    {
        self::$microtime = microtime(true);
        
        foreach (self::$tests as $test_name => $test_data) {
            if ($test_data['enabled'] && $test_name !== 'rank') {
                self::$files_count                    = 0;
                self::$tests[$test_name]['microtime'] = microtime(true);
                
                foreach (self::$files as $filename => $value) {
                    self::$files_count++;
                    $data = file_get_contents($filename);
                    call_user_func_array(array(
                        'self',
                        $test_data['func']
                    ), (array(
                        $data,
                        $filename
                    )));
                }
                
                self::$tests[$test_name]['microtime'] = microtime(true) - self::$tests[$test_name]['microtime'];
                self::$results[$test_name]            = self::rankCalculate(self::$results[$test_name]);
                usort(self::$results[$test_name], array(
                    'self',
                    'cmp'
                ));
            }
        }
        self::$microtime = microtime(true) - self::$microtime;
    }
    
    private static function cmp($a, $b)
    {
        if ($a['value'] == $b['value']) {
            return 0;
        }
        return ($a['value'] < $b['value']) ? 1 : -1;
    }
    
    private function reset()
    {
        self::$char_count       = array();
        self::$total_char_count = 0;
        self::$ic_total_results = 0;
    }
    
    private function clear()
    {
        self::$char_count       = array();
        self::$total_char_count = 0;
        self::$ic_total_results = 0;
        self::$results          = array();
    }
    
    private function calculate_char_count($data)
    {
        if (!$data)
            return 0;
        for ($i = 0; $i < 256; $i++) {
            $char      = chr($i);
            $charcount = substr_count($data, $char);
            if (!isset(self::$char_count[$char])) {
                self::$char_count[$char] = 0;
            }
            self::$char_count[$char] += $charcount;
            self::$total_char_count += $charcount;
        }
    }
    
    private function calculate_IC()
    {
        $total = 0;
        
        foreach (self::$char_count as $char => $value) {
            if ($value !== 0) {
                $total += $value * ($value - 1);
            }
        }
        
        try {
            $ic_total = $total / (self::$total_char_count * (self::$total_char_count - 1));
        }
        catch (Exception $e) {
            $ic_total = 0;
        }
        self::$ic_total_results = $ic_total;
    }
    
    public function ICCalculate($data, $filename)
    {
        if (!$data)
            return 0;
        
        $char_count       = 0;
        $total_char_count = 0;
        
        for ($i = 1; $i < 256; $i++) {
            $char      = chr($i);
            $charcount = substr_count($data, $char);
            $char_count += $charcount * ($charcount - 1);
            $total_char_count += $charcount;
        }
        
        $ic = $char_count / ($total_char_count * ($total_char_count - 1));
        
        self::$results['ic'][] = array(
            'filename' => $filename,
            'value' => $ic
        );
        
        self::calculate_char_count($data);
        
        return $ic;
    }
    
    public function entropyCalculate($data, $filename)
    {
        if (!$data)
            return 0;
        $entropy = 0;
        
        $stripped_data = str_replace(' ', '', $data);
        for ($i = 0; $i < 256; $i++) {
            $p_x = substr_count($stripped_data, chr($i)) / strlen($stripped_data);
            if ($p_x > 0) {
                $entropy += -$p_x * log($p_x, 2);
            }
        }
        self::$results['entropy'][] = array(
            'filename' => $filename,
            'value' => $entropy
        );
        
        return $entropy;
    }
    
    public function longestWordCalculate($data, $filename)
    {
        if (!$data)
            return 0;
        $longest      = 0;
        $longest_word = '';
        $words        = preg_split("/[\s,]*\\\"([^\\\"]+)\\\"[\s,]*|" . "[\s,]*'([^']+)'[\s,]*|" . "[\s,]+/", $data);
        if ($words) {
            foreach ($words as $word) {
                $length = strlen($word);
                if ($length > $longest) {
                    $longest      = $length;
                    $longest_word = $word;
                }
            }
        }
        
        self::$results['longestword'][] = array(
            'filename' => $filename,
            'value' => $longest
        );
        
        return $longest;
    }
    
    public function signatureNastyCalculate($data, $filename)
    {
        if (!$data)
            return 0;
        
        preg_match_all('/(eval\(|file_put_contents|base64_decode|python_eval|exec\(|passthru|popen|proc_open|pcntl|assert\(|system\(|shell)/', $data, $matches);
        
        self::$results['signature'][] = array(
            'filename' => $filename,
            'value' => count($matches)
        );
        
        return count($matches);
    }
    
    public function signatureSuperNastyCalculate($data, $filename)
    {
        if (!$data)
            return 0;
        
        preg_match_all('/(@\$_\[\]=|\$_=@\$_GET|\$_\[\+""\]=)/', $data, $matches);
        
        self::$results['supersignature'][] = array(
            'filename' => $filename,
            'value' => count($matches)
        );
        
        return count($matches);
    }
    
    public function usesEvalCalculate($data, $filename)
    {
        if (!$data)
            return 0;
        
        preg_match_all('/(eval\(\$(\w|\d))/', $data, $matches);
        
        self::$results['eval'][] = array(
            'filename' => $filename,
            'value' => count($matches)
        );
        
        return count($matches);
    }
    
    public function compressionCalculate($data, $filename)
    {
        if (!$data)
            return 0;
        
        $ratio = strlen(gzcompress($data)) / strlen($data);
        
        self::$results['zlib'][] = array(
            'filename' => $filename,
            'value' => $ratio
        );
        
        return $ratio;
    }
    
    private function rankCalculate($results)
    {
        $rank          = 1;
        $offset        = 1;
        $previousValue = false;
        if ($results) {
            foreach ($results as $key => $file) {
                if ($previousValue && $previousValue !== $file['value']) {
                    $rank = $offset;
                }
                $results[$key]['rank'] = $rank;
                $previousValue         = $file['value'];
                $offset++;
            }
        }
        return $results;
    }
    
    public function getFiles()
    {
        $directory = new recursiveDirectoryIterator(self::$path);
        
        $files = new RecursiveIteratorIterator($directory);
        
        $extensions = implode('|', self::$extensions);
        
        $regex = new RegexIterator($files, '/^.+\.(' . $extensions . ')$/i', RecursiveRegexIterator::GET_MATCH);
        foreach ($regex as $filename => $value) {
            if (is_file($filename)) {
                self::$files[$filename] = $value;
            }
        }
        if (empty(self::$files)) {
            die('Files not found.');
        }
        
        return self::$files;
    }
    
    public function generateRankStats()
    {
        foreach (self::$results as $test => $results) {
            for ($i = 0; $i < self::$report_limit; $i++) {
                if (!isset(self::$results['rank'][$results[$i]['filename']])) {
                    self::$results['rank'][$results[$i]['filename']] = $results[$i];
                } else {
                    self::$results['rank'][$results[$i]['filename']]['value'] += $results[$i]['rank'];
                }
            }
        }
        usort(self::$results['rank'], array(
            'self',
            'cmp'
        ));
    }
    
    public function report()
    {
        self::generateRankStats();
        
        $report = '[[ Scan time ' . round(self::$microtime, 2) . ' ]]' . PHP_EOL;
        $report .= '[[ Files Checked ' . self::$files_count . ' ]]' . PHP_EOL;
        foreach (self::$results as $test => $results) {
            $report .= PHP_EOL . sprintf(self::$tests[$test]['header'], (int) (self::$report_limit > count($results)) ? count($results) : self::$report_limit, round((self::$tests[$test]['microtime'] == 0) ? '' : self::$tests[$test]['microtime'], 2)) . PHP_EOL;
            $counter = 0;
            foreach ($results as $result) {
                if ($counter < self::$report_limit) {
                    $report .= round($result['value'], 3) . "\t" . realpath($result['filename']) . PHP_EOL;
                }
                $counter++;
            }
        }
        
        if (self::$csv) {
            $fp = fopen(__DIR__ . DIRECTORY_SEPARATOR . time() . '.csv', 'w');
            fwrite($fp, $report);
            fclose($fp);
        }
        
        if (!self::$isCLI) {
            $report = nl2br($report);
        }
        echo $report;
    }
}

$NeoPI = new NeoPI(true);
