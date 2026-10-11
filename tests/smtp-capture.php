<?php
declare(strict_types=1);
// No relay, authentication, internet access or real recipients. Started only by integration tests.
if(PHP_SAPI!=='cli'||count($argv)!==3){exit(2);}
$server=stream_socket_server('tcp://127.0.0.1:'.(int)$argv[1],$code,$error);if(!$server){exit(3);}
while($client=@stream_socket_accept($server,60)){
 stream_set_timeout($client,15);fwrite($client,"220 localhost test capture\r\n");$recipient='';
 while(($line=fgets($client))!==false){$command=strtoupper(strtok(trim($line),' '));
  if($command==='EHLO'||$command==='HELO'){fwrite($client,"250 localhost\r\n");}
  elseif($command==='MAIL'||$command==='RSET'||$command==='NOOP'){fwrite($client,"250 OK\r\n");}
  elseif($command==='RCPT'){if(preg_match('/<([^>]+)>/',$line,$m)&&str_ends_with(strtolower($m[1]),'@example.invalid')){$recipient=$m[1];fwrite($client,"250 OK\r\n");}else{fwrite($client,"550 Test recipients only\r\n");}}
  elseif($command==='DATA'&&$recipient!==''){fwrite($client,"354 End with dot\r\n");$data='';while(($part=fgets($client))!==false&&$part!==".\r\n"){$data.=$part;}file_put_contents($argv[2],json_encode(['recipient'=>$recipient,'message'=>$data],JSON_THROW_ON_ERROR)."\n",FILE_APPEND|LOCK_EX);fwrite($client,"250 Captured locally\r\n");}
  elseif($command==='QUIT'){fwrite($client,"221 Bye\r\n");break;}
  else{fwrite($client,"502 Unsupported\r\n");}
 }fclose($client);
}
