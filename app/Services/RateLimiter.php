<?php
declare(strict_types=1);
namespace App\Services;
final class RateLimiter
{
    public function __construct(private string $directory = BASE_PATH . '/storage/rate_limits') {}
    /** Both counters are reserved before password verification, even across sessions. */
    public function consume(string $email, string $ip): int
    { return $this->reserve(['email:' . strtolower(trim($email)) => 5, 'ip:' . $ip => 30]); }
    public function critical(string $action,string $identity,string $ip,int $limit=5):int
    { return $this->reserve(['critical:'.$action.':'.hash('sha256',$identity)=>$limit,'critical-ip:'.$action.':'.$ip=>max(30,$limit)]); }
    private function reserve(array $buckets):int
    {
        if(env('APP_ENV')==='testing'&&env('RATE_LIMIT_DIRECTORY')!==''){$this->directory=env('RATE_LIMIT_DIRECTORY');}
        if (!is_dir($this->directory) && !mkdir($this->directory, 0700, true) && !is_dir($this->directory)) { throw new \RuntimeException('Hız sınırı deposu açılamadı.'); }
        $retry = 0;
        foreach ($buckets as $key => $limit) {
            $file = fopen($this->directory . '/' . hash('sha256', $key) . '.json', 'c+');
            if (!$file || !flock($file, LOCK_EX)) { throw new \RuntimeException('Hız sınırı kilidi açılamadı.'); }
            try {
                $data = json_decode(stream_get_contents($file), true);
                $now = time();
                if (!is_array($data) || ($data['expires'] ?? 0) <= $now) { $data = ['expires' => $now + 900, 'count' => 0]; }
                if ($data['count'] >= $limit) { $retry = max($retry, $data['expires'] - $now); }
                else { $data['count']++; }
                rewind($file); ftruncate($file, 0);
                if (fwrite($file, json_encode($data, JSON_THROW_ON_ERROR)) === false || !fflush($file)) { throw new \RuntimeException('Hız sınırı kaydedilemedi.'); }
            } finally { flock($file, LOCK_UN); fclose($file); }
        }
        return $retry;
    }
}
