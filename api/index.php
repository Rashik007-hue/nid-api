<?php
error_reporting(E_ALL);
ini_set('display_errors', 0); // প্রোডাকশনে অফ
ini_set('log_errors', 1);
ini_set('error_log', sys_get_temp_dir() . '/error.log'); // Vercel এরর লগ ফিক্স

header('Content-Type: application/json; charset=utf-8');

try {
    // ==================== কনফিগারেশন ====================
    define('BASE_URL', 'https://lsg-land-owner-stage.land.gov.bd');
    define('API_URL', BASE_URL . '/check/user/nid/verification');
    define('SECRET_KEY', 'lsg56xy14yu45dfgy124dfe12dr52fgd');
    
    // 🔴 Vercel Path Fix: অরিজিনাল ফাইল রিড করবে, কিন্তু রাইট করবে /tmp ফোল্ডারে
    define('SOURCE_ACCOUNTS_FILE', __DIR__ . '/accounts.json');
    define('ACCOUNTS_FILE', sys_get_temp_dir() . '/accounts.json');
    
    // প্রথমবার রান হলে অরিজিনাল accounts.json থেকে ডাটা কপি করবে
    if (!file_exists(ACCOUNTS_FILE) && file_exists(SOURCE_ACCOUNTS_FILE)) {
        copy(SOURCE_ACCOUNTS_FILE, ACCOUNTS_FILE);
    }
    
    define('USER_AGENT', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36');

    // Helper ফাইল include করুন
    if (!file_exists(__DIR__ . '/Helper.php')) {
        throw new Exception('Helper.php file not found');
    }
    require_once __DIR__ . '/Helper.php';

    // ==================== NID Lookup Class ====================
    class NIDLookup {
        private $cookie_jar;
        private $base_url = 'https://services.nidw.gov.bd/nid-pub';
        private $user_agent = 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Mobile Safari/537.36';
        private $csrf_token = '';
        private $ocr_api_key = 'K87822842088957';
        
        private $districtToDivision = [
            'ঢাকা' => 'ঢাকা', 'গাজীপুর' => 'ঢাকা', 'নারায়ণগঞ্জ' => 'ঢাকা', 'মানিকগঞ্জ' => 'ঢাকা',
            'মুন্সীগঞ্জ' => 'ঢাকা', 'নরসিংদী' => 'ঢাকা', 'টাঙ্গাইল' => 'ঢাকা', 'ফরিদপুর' => 'ঢাকা',
            'গোপালগঞ্জ' => 'ঢাকা', 'মাদারীপুর' => 'ঢাকা', 'শরীয়তপুর' => 'ঢাকা', 'রাজবাড়ী' => 'ঢাকা',
            'চট্টগ্রাম' => 'চট্টগ্রাম', 'কুমিল্লা' => 'চট্টগ্রাম', 'ফেনী' => 'চট্টগ্রাম',
            'ব্রাহ্মণবাড়িয়া' => 'চট্টগ্রাম', 'রাঙ্গামাটি' => 'চট্টগ্রাম', 'নোয়াখালী' => 'চট্টগ্রাম',
            'চাঁদপুর' => 'চট্টগ্রাম', 'লক্ষ্মীপুর' => 'চট্টগ্রাম', 'কক্সবাজার' => 'চট্টগ্রাম',
            'খাগড়াছড়ি' => 'চট্টগ্রাম', 'বান্দরবান' => 'চট্টগ্রাম',
            'রাজশাহী' => 'রাজশাহী', 'নাটোর' => 'রাজশাহী', 'নওগাঁ' => 'রাজশাহী',
            'চাঁপাইনবাবগঞ্জ' => 'রাজশাহী', 'পাবনা' => 'রাজশাহী', 'সিরাজগঞ্জ' => 'রাজশাহী',
            'বগুড়া' => 'রাজশাহী', 'জয়পুরহাট' => 'রাজশাহী',
            'খুলনা' => 'খুলনা', 'বাগেরহাট' => 'খুলনা', 'সাতক্ষীরা' => 'খুলনা', 'যশোর' => 'খুলনা',
            'চুয়াডাঙ্গা' => 'খুলনা', 'কুষ্টিয়া' => 'খুলনা', 'মেহেরপুর' => 'খুলনা',
            'মাগুরা' => 'খুলনা', 'ঝিনাইদহ' => 'খুলনা', 'নড়াইল' => 'খুলনা',
            'বরিশাল' => 'বরিশাল', 'পটুয়াখালী' => 'বরিশাল', 'ভোলা' => 'বরিশাল',
            'পিরোজপুর' => 'বরিশাল', 'বরগুনা' => 'বরিশাল', 'ঝালকাঠি' => 'বরিশাল',
            'সিলেট' => 'সিলেট', 'মৌলভীবাজার' => 'সিলেট', 'হবিগঞ্জ' => 'সিলেট', 'সুনামগঞ্জ' => 'সিলেট',
            'রংপুর' => 'রংপুর', 'দিনাজপুর' => 'রংপুর', 'গাইবান্ধা' => 'রংপুর', 'কুড়িগ্রাম' => 'রংপুর',
            'নীলফামারী' => 'রংপুর', 'লালমনিরহাট' => 'রংপুর', 'ঠাকুরগাঁও' => 'রংপুর', 'পঞ্চগড়' => 'রংপুর',
            'ময়মনসিংহ' => 'ময়মনসিংহ', 'নেত্রকোণা' => 'ময়মনসিংহ', 'জামালপুর' => 'ময়মনসিংহ', 'শেরপুর' => 'ময়মনসিংহ'
        ];
        
        public function __construct() {
            $this->cookie_jar = tempnam(sys_get_temp_dir(), 'nid_cookie_');
            if ($this->cookie_jar === false) {
                throw new Exception('Unable to create temporary cookie file');
            }
        }
        
        private function getDivisionFromDistrict($district) {
            return $this->districtToDivision[trim($district)] ?? '';
        }
        
        private function getCSRFToken() {
            $ch = curl_init($this->base_url . '/card-status');
            if ($ch === false) {
                throw new Exception('Curl initialization failed');
            }
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_COOKIEJAR => $this->cookie_jar,
                CURLOPT_COOKIEFILE => $this->cookie_jar,
                CURLOPT_USERAGENT => $this->user_agent,
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_TIMEOUT => 30
            ]);
            $resp = curl_exec($ch);
            $error = curl_error($ch);
            curl_close($ch);
            
            if ($resp === false) {
                throw new Exception('CSRF token fetch failed: ' . $error);
            }
            
            preg_match('/name="_csrf" content="([^"]+)"/', $resp, $match);
            $this->csrf_token = $match[1] ?? '';
            return $this->csrf_token;
        }
        
        private function downloadCaptcha() {
            $url = $this->base_url . '/captcha/?t=' . time();
            
            $ch = curl_init($url);
            if ($ch === false) {
                throw new Exception('Curl initialization failed for captcha');
            }
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_COOKIEJAR => $this->cookie_jar,
                CURLOPT_COOKIEFILE => $this->cookie_jar,
                CURLOPT_USERAGENT => $this->user_agent,
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_REFERER => $this->base_url . '/card-status',
                CURLOPT_TIMEOUT => 30,
                CURLOPT_HTTPHEADER => ['Accept: image/avif,image/webp,image/apng,image/*,*/*;q=0.8']
            ]);
            $image_data = curl_exec($ch);
            $error = curl_error($ch);
            curl_close($ch);
            
            if (empty($image_data)) {
                throw new Exception('Captcha download failed: ' . $error);
            }
            
            $captcha = imagecreatefromstring($image_data);
            if ($captcha === false) {
                throw new Exception('Failed to create image from captcha data. (GD extension missing?)');
            }
            
            $width = imagesx($captcha);
            $height = imagesy($captcha);
            
            $background = imagecreatetruecolor($width, $height);
            if ($background === false) {
                imagedestroy($captcha);
                throw new Exception('Failed to create background image');
            }
            $white = imagecolorallocate($background, 255, 255, 255);
            imagefill($background, 0, 0, $white);
            imagecopy($background, $captcha, 0, 0, 0, 0, $width, $height);
            
            $temp_file = tempnam(sys_get_temp_dir(), 'captcha_') . '.png';
            if ($temp_file === false) {
                imagedestroy($captcha);
                imagedestroy($background);
                throw new Exception('Failed to create temporary captcha file');
            }
            imagepng($background, $temp_file);
            
            imagedestroy($captcha);
            imagedestroy($background);
            
            return $temp_file;
        }
        
        private function solveCaptcha($image_path) {
            if (!file_exists($image_path)) {
                throw new Exception('Captcha image file not found');
            }
            $image_data = base64_encode(file_get_contents($image_path));
            
            $ch = curl_init('https://api.ocr.space/parse/image');
            if ($ch === false) {
                throw new Exception('Curl initialization failed for OCR');
            }
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => [
                    'apikey' => $this->ocr_api_key,
                    'language' => 'eng',
                    'base64Image' => 'data:image/png;base64,' . $image_data,
                    'OCREngine' => 3,
                    'scale' => true
                ],
                CURLOPT_TIMEOUT => 30
            ]);
            $resp = curl_exec($ch);
            $error = curl_error($ch);
            curl_close($ch);
            
            if ($resp === false) {
                throw new Exception('OCR API call failed: ' . $error);
            }
            
            $data = json_decode($resp, true);
            $text = $data['ParsedResults'][0]['ParsedText'] ?? '';
            return preg_replace('/[^a-zA-Z0-9]/', '', trim($text));
        }
        
        public function lookupNID($nid, $day, $month, $year) {
            if (empty($this->csrf_token)) {
                $this->getCSRFToken();
            }
            
            if (empty($this->csrf_token)) {
                return ['success' => false, 'message' => 'CSRF token not found'];
            }
            
            $captcha_image = $this->downloadCaptcha();
            $captcha_text = $this->solveCaptcha($captcha_image);
            if (strlen($captcha_text) < 3) {
                return ['success' => false, 'message' => 'ক্যাপচা সলভ ব্যর্থ!'];
            }
            
            $post_data = http_build_query([
                'nid' => $nid,
                'day' => $day,
                'month' => $month,
                'year' => $year,
                'captcha' => $captcha_text
            ]);
            
            $ch = curl_init($this->base_url . '/card-status/validate');
            if ($ch === false) {
                throw new Exception('Curl initialization failed for validation');
            }
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => $post_data,
                CURLOPT_COOKIEJAR => $this->cookie_jar,
                CURLOPT_COOKIEFILE => $this->cookie_jar,
                CURLOPT_USERAGENT => $this->user_agent,
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_REFERER => $this->base_url . '/card-status',
                CURLOPT_TIMEOUT => 30,
                CURLOPT_HTTPHEADER => [
                    'Content-Type: application/x-www-form-urlencoded; charset=UTF-8',
                    'X-CSRF-TOKEN: ' . $this->csrf_token,
                    'X-Requested-With: XMLHttpRequest',
                    'Accept: application/json, text/plain, */*'
                ]
            ]);
            $resp = curl_exec($ch);
            $error = curl_error($ch);
            curl_close($ch);
            
            if ($resp === false) {
                return ['success' => false, 'message' => 'Validation request failed: ' . $error];
            }
            
            $result = json_decode($resp, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                return ['success' => false, 'message' => 'Invalid JSON response from NID service'];
            }
            
            if ($result['status'] === 'SUCCESS' && isset($result['success']['template'])) {
                return $this->getCardDetails($result['success']['template'], $nid, $day, $month, $year);
            }
            
            return ['success' => false, 'message' => $result['error'] ?? 'অজানা এরর!'];
        }
        
        private function getCardDetails($template_url, $nid, $day, $month, $year) {
            $url = $this->base_url . $template_url . '?t=' . time();
            
            $ch = curl_init($url);
            if ($ch === false) {
                throw new Exception('Curl initialization failed for card details');
            }
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_COOKIEJAR => $this->cookie_jar,
                CURLOPT_COOKIEFILE => $this->cookie_jar,
                CURLOPT_USERAGENT => $this->user_agent,
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_REFERER => $this->base_url . '/card-status',
                CURLOPT_TIMEOUT => 30,
                CURLOPT_HTTPHEADER => [
                    'X-CSRF-TOKEN: ' . $this->csrf_token,
                    'X-Requested-With: XMLHttpRequest',
                    'Accept: text/html, */*'
                ]
            ]);
            $html = curl_exec($ch);
            $error = curl_error($ch);
            curl_close($ch);
            
            if ($html === false) {
                return ['success' => false, 'message' => 'Card details fetch failed: ' . $error];
            }
            
            return $this->extractData($html, $nid, $day, $month, $year);
        }
        
        private function extractData($html, $nid, $day, $month, $year) {
            $dom = new DOMDocument();
            @$dom->loadHTML(mb_convert_encoding($html, 'HTML-ENTITIES', 'UTF-8'));
            $xpath = new DOMXPath($dom);
            
            $rows = $xpath->query('//table//tr');
            
            $data = [
                'status' => '',
                'box_id' => '',
                'comp_id' => '',
                'district' => '',
                'upozila' => '',
                'voter_area' => '',
                'contact_address' => ''
            ];
            
            foreach ($rows as $row) {
                $cells = $xpath->query('.//td', $row);
                if ($cells->length >= 7) {
                    $data['status'] = trim($cells->item(0)->textContent);
                    $data['box_id'] = trim($cells->item(1)->textContent);
                    $data['comp_id'] = trim($cells->item(2)->textContent);
                    $data['district'] = trim($cells->item(3)->textContent);
                    $data['upozila'] = trim($cells->item(4)->textContent);
                    $data['voter_area'] = trim($cells->item(5)->textContent);
                    $data['contact_address'] = trim($cells->item(6)->textContent);
                    break;
                }
            }
            
            foreach ($data as $key => $value) {
                $data[$key] = str_replace(['N/A', 'Complete'], '', $value);
                $data[$key] = trim($data[$key]);
            }
            
            $village = $data['voter_area'];
            if (!empty($village)) {
                if (preg_match('/গ্রাম\/রাস্তা\s*:?\s*([^,]+)/i', $village, $match)) {
                    $village = trim($match[1]);
                } else {
                    $village = preg_replace('/বাসা\/হোল্ডিং\s*:?\s*[^,]+,?\s*/i', '', $village);
                    $village = preg_replace('/মৌজা\/মহল্লা\s*:?\s*[^,]+,?\s*/i', '', $village);
                    $village = trim($village);
                }
            }
            
            $division = $this->getDivisionFromDistrict($data['district']);
            
            $address = "বাসা/হোল্ডিং: , গ্রাম/রাস্তা: {$village}";
            if (!empty($data['upozila'])) {
                $address .= ", উপজেলা: {$data['upozila']}";
            }
            if (!empty($data['district'])) {
                $address .= ", জেলা: {$data['district']}";
            }
            if (!empty($division)) {
                $address .= ", বিভাগ: {$division}";
            }
            
            $dob_formatted = sprintf("%04d-%02d-%02d", $year, $month, $day);
            $dayOfWeek = $this->getDayOfWeek($year, $month, $day);
            $age = $this->calculateAge($year, $month, $day);
            
            return [
                'success' => true,
                'message' => 'NID information retrieved successfully',
                'data' => [
                    'nid' => $nid,
                    'dob' => $dob_formatted,
                    'day_of_week' => $dayOfWeek,
                    'age' => $age,
                    'village' => $village,
                    'upozila' => $data['upozila'],
                    'district' => $data['district'],
                    'division' => $division,
                    'address' => $address,
                    'voter_area' => $data['voter_area']
                ]
            ];
        }
        
        private function getDayOfWeek($year, $month, $day) {
            $timestamp = mktime(0, 0, 0, $month, $day, $year);
            $dayIndex = date('w', $timestamp);
            
            $banglaDays = [
                0 => 'রবিবার', 1 => 'সোমবার', 2 => 'মঙ্গলবার', 3 => 'বুধবার',
                4 => 'বৃহস্পতিবার', 5 => 'শুক্রবার', 6 => 'শনিবার'
            ];
            
            return $banglaDays[$dayIndex] ?? '';
        }
        
        private function calculateAge($birthYear, $birthMonth, $birthDay) {
            $today = new DateTime();
            $birthDate = new DateTime("{$birthYear}-{$birthMonth}-{$birthDay}");
            $diff = $today->diff($birthDate);
            
            $banglaNumbers = ['০','১','২','৩','৪','৫','৬','৭','৮','৯'];
            $years = str_replace(range(0,9), $banglaNumbers, (string)$diff->y);
            $months = str_replace(range(0,9), $banglaNumbers, (string)$diff->m);
            $days = str_replace(range(0,9), $banglaNumbers, (string)$diff->d);
            
            return "{$years} বছর {$months} মাস {$days} দিন";
        }
    }

    // ==================== হেলপার ফাংশন ====================
    if (!function_exists('encrypt_data')) {
        function encrypt_data($plaintext, $secret_key) {
            $iv_bytes = openssl_random_pseudo_bytes(16);
            $encrypted = openssl_encrypt(
                $plaintext,
                'aes-256-cbc',
                $secret_key,
                OPENSSL_RAW_DATA,
                $iv_bytes
            );
            return [
                'iv' => base64_encode($iv_bytes),
                'encryptedData' => base64_encode($encrypted)
            ];
        }
    }

    function getAccounts() {
        if (!file_exists(ACCOUNTS_FILE)) return [];
        $data = json_decode(file_get_contents(ACCOUNTS_FILE), true);
        return is_array($data) ? $data : [];
    }

    function updateAccountAttempt($username) {
        $accounts = getAccounts();
        foreach ($accounts as &$account) {
            if ($account['username'] === $username) {
                $account['attempt_increased'] = ($account['attempt_increased'] ?? 0) + 1;
                $account['attempt_increased_at'] = date('Y-m-d H:i:s');
                break;
            }
        }
        file_put_contents(ACCOUNTS_FILE, json_encode($accounts, JSON_PRETTY_PRINT));
    }

    function resetDailyAttempts() {
        $accounts = getAccounts();
        $today = date('Y-m-d');
        $modified = false;
        
        foreach ($accounts as &$account) {
            if (isset($account['attempt_increased_at'])) {
                $attempt_date = date('Y-m-d', strtotime($account['attempt_increased_at']));
                if ($attempt_date !== $today) {
                    $account['attempt_increased'] = 0;
                    $account['attempt_increased_at'] = date('Y-m-d H:i:s');
                    $modified = true;
                }
            }
        }
        
        if ($modified) {
            file_put_contents(ACCOUNTS_FILE, json_encode($accounts, JSON_PRETTY_PRINT));
        }
    }

    // ==================== লগইন ফাংশন ====================
    function loginWithAccount($username, $password) {
        $login_url = BASE_URL . '/login';
        
        $ch = curl_init();
        if ($ch === false) {
            return false;
        }
        curl_setopt_array($ch, [
            CURLOPT_URL => $login_url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER => true,
            CURLOPT_USERAGENT => USER_AGENT,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_TIMEOUT => 15,
        ]);
        $response = curl_exec($ch);
        $header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        curl_close($ch);
        
        if (!$response) return false;
        
        $body = substr($response, $header_size);
        preg_match('/<meta name="csrf-token" content="([^"]+)"/i', $body, $m);
        $csrf_token = $m[1] ?? '';
        if (!$csrf_token) return false;
        
        $headers = substr($response, 0, $header_size);
        $cookies = [];
        foreach (explode("\n", $headers) as $line) {
            if (stripos($line, 'Set-Cookie:') === 0) {
                $cookie = trim(substr($line, 11));
                $pair = explode(';', $cookie, 2)[0];
                if (strpos($pair, '=') !== false) {
                    [$k, $v] = explode('=', $pair, 2);
                    $cookies[$k] = $v;
                }
            }
        }
        
        $cookie_string = '';
        foreach ($cookies as $k => $v) {
            $cookie_string .= "$k=$v; ";
        }
        $cookie_string = rtrim($cookie_string, '; ');
        
        $postData = [
            '_token' => $csrf_token,
            'userOptionType' => 'Citizen',
            'identity' => 'email',
            'username' => $username,
            'password' => $password,
            'qr-container-token' => ''
        ];
        
        $ch = curl_init();
        if ($ch === false) {
            return false;
        }
        curl_setopt_array($ch, [
            CURLOPT_URL => $login_url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query($postData),
            CURLOPT_USERAGENT => USER_AGENT,
            CURLOPT_HTTPHEADER => [
                'Cookie: ' . $cookie_string,
                'Content-Type: application/x-www-form-urlencoded'
            ],
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_TIMEOUT => 15,
            CURLOPT_FOLLOWLOCATION => false,
        ]);
        $login_response = curl_exec($ch);
        $login_http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $login_header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        curl_close($ch);
        
        if ($login_http_code != 302) return false;
        
        $login_headers = substr($login_response, 0, $login_header_size);
        $final_cookies = [];
        foreach (explode("\n", $login_headers) as $line) {
            if (stripos($line, 'Set-Cookie:') === 0) {
                $cookie = trim(substr($line, 11));
                $pair = explode(';', $cookie, 2)[0];
                if (strpos($pair, '=') !== false) {
                    [$k, $v] = explode('=', $pair, 2);
                    $final_cookies[$k] = $v;
                }
            }
        }
        
        if (!isset($final_cookies['lsg_session'])) {
            if (isset($cookies['lsg_session'])) {
                $final_cookies['lsg_session'] = $cookies['lsg_session'];
            } else {
                return false;
            }
        }
        
        $landing_url = BASE_URL . '/landing';
        $ch = curl_init();
        if ($ch === false) {
            return false;
        }
        curl_setopt_array($ch, [
            CURLOPT_URL => $landing_url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER => true,
            CURLOPT_USERAGENT => USER_AGENT,
            CURLOPT_HTTPHEADER => ['Cookie: lsg_session=' . $final_cookies['lsg_session']],
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_TIMEOUT => 15,
        ]);
        $landing_response = curl_exec($ch);
        $landing_header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        curl_close($ch);
        
        if (!$landing_response) return false;
        
        $landing_body = substr($landing_response, $landing_header_size);
        preg_match('/<meta name="csrf-token" content="([^"]+)"/i', $landing_body, $m2);
        $landing_csrf = $m2[1] ?? '';
        
        if (!$landing_csrf) return false;
        
        return [
            'csrf_token' => $landing_csrf,
            'lsg_session' => $final_cookies['lsg_session']
        ];
    }

    // ==================== API কল ফাংশন ====================
    function callVerificationAPI($nid, $dob, $session_data) {
        $plaintext = json_encode(['dob' => $dob, 'nid' => $nid]);
        $encrypted = encrypt_data($plaintext, SECRET_KEY);
        $postData = ['tokenized_en_data' => json_encode($encrypted)];
        
        $ch = curl_init();
        if ($ch === false) {
            return false;
        }
        curl_setopt_array($ch, [
            CURLOPT_URL => API_URL,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query($postData),
            CURLOPT_USERAGENT => USER_AGENT,
            CURLOPT_HTTPHEADER => [
                'Cookie: lsg_session=' . $session_data['lsg_session'],
                'x-csrf-token: ' . $session_data['csrf_token'],
                'Content-Type: application/x-www-form-urlencoded'
            ],
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_TIMEOUT => 20,
        ]);
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if (!$response) return false;
        
        return json_decode($response, true);
    }

    // ==================== মেইন প্রসেস ====================
    resetDailyAttempts();

    $nid = $_GET['nid'] ?? '';
    $dob = $_GET['dob'] ?? '';

    if (!$nid || !$dob) {
        echo json_encode([
            'code' => 400,
            'success' => false,
            'message' => 'nid and dob required',
            'usage' => '?nid=7314475786&dob=1991-11-07'
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        exit;
    }

    // প্রথমে NID Lookup থেকে ঠিকানা তথ্য নিন
    $dateParts = explode('-', $dob);
    $year = $dateParts[0] ?? '';
    $month = $dateParts[1] ?? '';
    $day = $dateParts[2] ?? '';

    if (!checkdate((int)$month, (int)$day, (int)$year)) {
        echo json_encode([
            'code' => 400,
            'success' => false,
            'message' => 'Invalid date format. Use YYYY-MM-DD'
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        exit;
    }

    $lookup = new NIDLookup();
    $address_result = $lookup->lookupNID($nid, $day, $month, $year);

    // লগইন সিস্টেম
    $accounts = getAccounts();
    $active_accounts = array_filter($accounts, function($acc) {
        return ($acc['attempt_increased'] ?? 0) < 10;
    });

    if (empty($active_accounts)) {
        echo json_encode([
            'code' => 429,
            'success' => false,
            'message' => 'All accounts reached daily limit'
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        exit;
    }

    $response_data = null;
    $used_account = null;
    $last_error = null;

    foreach ($active_accounts as $account) {
        $session = loginWithAccount($account['username'], $account['password']);
        if (!$session) { 
            $last_error = 'Login failed for: ' . $account['username']; 
            continue; 
        }
        
        $api_response = callVerificationAPI($nid, $dob, $session);
        if ($api_response === false) { 
            $last_error = 'API call failed for: ' . $account['username']; 
            continue; 
        }
        
        if (isset($api_response['success']) && $api_response['success'] === "false" &&
            isset($api_response['data']) && strpos($api_response['data'], 'সর্বোচ্চ সংখ্যকবার') !== false) {
            $accounts = getAccounts();
            foreach ($accounts as &$acc) {
                if ($acc['username'] === $account['username']) {
                    $acc['attempt_increased'] = 10;
                    break;
                }
            }
            file_put_contents(ACCOUNTS_FILE, json_encode($accounts, JSON_PRETTY_PRINT));
            $last_error = 'Daily limit reached for: ' . $account['username'];
            continue;
        }
        
        $response_data = $api_response;
        $used_account = $account['username'];
        break;
    }

    // ফাইনাল রেসপন্স
    if ($response_data && isset($response_data['success']) && $response_data['success'] === 'true') {
        if ($used_account) {
            updateAccountAttempt($used_account);
        }
        
        $nameEn = $response_data['citizen_info']['name_en'] ?? '';
        $religionGender = detectReligionAndGender($nameEn);
        
        // voter_area থেকে শুধু গ্রামের নাম নাও
        $raw_voter_area = $address_result['data']['voter_area'] ?? '';
        $village_name = $raw_voter_area;
        if (!empty($raw_voter_area)) {
            if (preg_match('/গ্রাম\/রাস্তা\s*:?\s*([^,]+)/i', $raw_voter_area, $match)) {
                $village_name = trim($match[1]);
            } else {
                $parts = explode(',', $raw_voter_area);
                $village_name = trim($parts[0]);
            }
        }
        
        $final_data = [
            'name' => $response_data['citizen_info']['name'] ?? '',
            'nameEn' => $nameEn,
            'nationalId' => $nid,
            'dateOfBirth' => $dob,
            'pin' => $year . $nid,
            'old_nid' => $nid,
            'voter_area' => $village_name,
            'birth_place' => $address_result['data']['district'] ?? '',
            'fatherName' => $response_data['citizen_info']['father_name'] ?? '',
            'motherName' => $response_data['citizen_info']['mother_name'] ?? '',
            'gender' => $religionGender['gender'] ?? 'N/A',
            'genderEn' => $religionGender['genderEn'] ?? 'N/A',
            'religion' => $religionGender['religion'] ?? 'N/A',
            'religionEn' => $religionGender['religionEn'] ?? 'N/A'
        ];
        
        if ($address_result['success']) {
            $final_data['village'] = $address_result['data']['village'] ?? '';
            $final_data['upozila'] = $address_result['data']['upozila'] ?? '';
            $final_data['district'] = $address_result['data']['district'] ?? '';
            $final_data['division'] = $address_result['data']['division'] ?? '';
            $final_data['address'] = $address_result['data']['address'] ?? '';
            $final_data['day_of_week'] = $address_result['data']['day_of_week'] ?? '';
            $final_data['age'] = $address_result['data']['age'] ?? '';
        }
        
        echo json_encode([
            'code' => 200,
            'success' => true,
            'message' => 'NID fetched successfully!',
            'data' => $final_data
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        
    } else {
        echo json_encode([
            'code' => 500,
            'success' => false,
            'message' => $response_data['data'] ?? $last_error ?? 'Verification failed',
            'data' => null
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    }

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'code' => 500,
        'success' => false,
        'message' => 'Internal Server Error: ' . $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        'data' => null
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
}
?>
