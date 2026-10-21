<?php

function getProtocol() {
    return isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
}

function getHost() {
    return $_SERVER['HTTP_HOST'];
}

function getScriptDir() {
    return dirname($_SERVER['SCRIPT_NAME']);
}

function curlRequest($url, $isPost = false, $postData = [], $userAgent = '', $withHeaders = false, $customHeaders = []) {
    $ch = curl_init();
    
    $options = [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HEADER => $withHeaders,
        CURLOPT_USERAGENT => $userAgent,
        CURLOPT_ENCODING => "gzip, deflate",
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_2_0,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_TIMEOUT => 20,
        CURLOPT_FOLLOWLOCATION => false,
    ];
    
    if ($isPost) {
        $options[CURLOPT_POST] = true;
        $options[CURLOPT_POSTFIELDS] = http_build_query($postData);
    }
    
    if (!empty($customHeaders)) {
        $options[CURLOPT_HTTPHEADER] = $customHeaders;
    }
    
    curl_setopt_array($ch, $options);
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if (!$response) return false;
    
    if ($withHeaders) {
        $header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        return [
            'headers' => substr($response, 0, $header_size),
            'body' => substr($response, $header_size),
            'http_code' => $http_code
        ];
    }
    
    return $response;
}

function parseCookies($headers) {
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
    return $cookies;
}

function buildCookieHeader($cookies) {
    $out = [];
    foreach ($cookies as $k => $v) {
        $out[] = "$k=$v";
    }
    return implode('; ', $out);
}

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

function detectReligionAndGender($name) {
    $islamicPrefixes = ['md', 'mohammad', 'muhammad', 'mst'];

    $maleIslamicPatterns = [
        'md','mohammad','muhammad','ahmed','ahmad','ali','hasan','hussain','hassan','hussein',
        'abdullah','abdul','rahman','abdulrahman','abdulaziz','omar','umar','osman','usman',
        'ibrahim','ishaq','yasir','yusuf','yamin','zakaria','sulaiman','dawood','musa','harun','yunus',
        'ayub','idris','ilyas','ismail','yaqub','shuaib','saleh','hud','taha','jalal','kamal',
        'karim','rahim','jabbar','hafiz','qari','imam','sheikh','maulana','khalid','khalil',
        'rashid','salim','salman','talha','zubair','farooq','farhan','fahad','saad','hamza',
        'imran','irfan','junaid','kashif','nadeem','nasir','owais','qasim','raheel','raza',
        'rizwan','shahid','shakeel','shams','sharif','shoaib','sohail','sultan','tahir',
        'wajid','zahid','zia','zulfiqar','hafeez','mateen','mustafa','murad','najeeb',
        'noor','nur','parvez','qadeer','rafiq','rauf','rehman','sajjad','sarwar','subhan',
        'umar','usama','waleed','waseem','younus','zafar','zaheer','zain','zeshan'
    ];

    $femaleIslamicPatterns = [
        'mst','fatima','ayesha','aisha','zahra','khadija','hafsa','sumaiya','asia','asiya',
        'rabia','saira','zainab','maryam','mariam','kulsum','ruqayya','umm','bibi','begum',
        'noor','nur','afreen','alina','amina','aneesa','areeba','asma','atiya','azra',
        'bushra','dania','fariha','farzana','fauzia','haleema','halima','hana','hareem',
        'hasina','humaira','iqra','javeria','kaniz','karima','laila','lubna','madiha',
        'mahnoor','malaika','marwa','maya','maymuna','mehnaz','muneeba','nadia','nafisa',
        'naila','najma','nazia','nida','nimra','parveen','rabia','rida','roshni','rubina',
        'saba','sadia','safa','safia','saima','sajida','saleha','samina','sana','sania',
        'sara','shabana','shagufta','shazia','sidra','sobia','sofia','tabinda','tahira',
        'taiba','tasneem','tuba','umaima','uzma','warda','yasmeen','yusra','zahida',
        'zara','zubaida','zulekha','zumra'
    ];

    $maleHinduPatterns = [
        'ram','krishna','shyam','hari','gopal','govinda','shiv','shiva','mahadev','shankar',
        'vishnu','narayan','madhav','arjun','bheem','bhim','karna','abhimanyu',
        'ganesh','vinayak','laxman','lakshman','bharat','hanuman',
        'indra','surya','chandra','ashok','ajay','amit','anil','arun','atul',
        'deepak','dinesh','girish','harish','kamal','kumar','manoj','mukesh',
        'naresh','prakash','rajesh','ramesh','suresh','sanjay','sunil',
        'vinod','vijay','vikram','rahul','rohit','sumit','tarun','yogesh'
    ];

    $femaleHinduPatterns = [
        'sita','radha','radhika','gita','geeta','parvati','durga','kali','lakshmi','laxmi',
        'saraswati','uma','gauri','savita','sunita','sarita','kavita','anjali','archana',
        'babita','bina','deepa','indira','kamala','kiran','lalita','madhuri','manju',
        'meenakshi','meera','nirmala','pooja','puja','priya','rekha','rina','ritu',
        'sadhana','sandhya','shanta','sharmila','shobha','shreya','smita','sneha',
        'sudha','sushma','usha','vidya','yamini','anita','kajal','payal'
    ];

    $nameLower = strtolower($name);

    $isIslamic = false;
    $allIslamicPatterns = array_merge($maleIslamicPatterns, $femaleIslamicPatterns, $islamicPrefixes);

    foreach ($allIslamicPatterns as $pattern) {
        if (strpos($nameLower, $pattern) !== false) {
            $isIslamic = true;
            break;
        }
    }

    $gender = 'Unknown';

    foreach ($maleIslamicPatterns as $pattern) {
        if (strpos($nameLower, $pattern) !== false) {
            $gender = 'Male';
            break;
        }
    }

    if ($gender === 'Unknown') {
        foreach ($femaleIslamicPatterns as $pattern) {
            if (strpos($nameLower, $pattern) !== false) {
                $gender = 'Female';
                break;
            }
        }
    }

    return [
        'religion' => $isIslamic ? 'ইসলাম' : 'N/A',
        'religionEn' => $isIslamic ? 'Islam' : 'N/A',
        'gender' => $gender === 'Unknown' ? 'N/A' : ($gender === 'Male' ? 'পুরুষ' : 'নারী'),
        'genderEn' => $gender === 'Unknown' ? 'N/A' : $gender
    ];
}
