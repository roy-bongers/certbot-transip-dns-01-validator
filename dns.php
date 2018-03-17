<?php // PHP DNS Direct Query Module
/* -------------------------------------------------------------
This file is the PurplePixie PHP DNS Query Classes

The software is (C) Copyright 2008-14 PurplePixie Systems

This is free software: you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation, either version 3 of the License, or
(at your option) any later version.

The software is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this software.  If not, see www.gnu.org/licenses

For more information see www.purplepixie.org/phpdns
-------------------------------------------------------------- */

//
// Version 1.05		30th December 2014
// David Cutting -- dcutting (some_sort_of_at_sign) purplepixie dot org
//
// D Tucny - 2011-06-21 - Add lots more types with RFC references
// D Tucny - 2011-06-21 - Add decoding of IPv6 addresses in AAAA RRs and sorted handling alphabetically
// D Tucny - 2011-06-21 - Switch to using inet_ntop function where available to decode IP addresses
// 			and include binary IP in extras
// D Tucny - 2011-06-21 - Add fallback inet_ntop function (sourced from php.net) for
//			platforms missing it
// D Tucny - 2011-06-21 - Correct string extraction for TXT records 
//			(a TXT record can contain one of more 255 character strings 
//			with an initial length byte that are concatenated togeter) 
//			the length bytes were being included in the result as part of the text
// D Tucny - 2011-06-21 - Add decoding of SPF as an alias of TXT due to identical formatting
// D Tucny - 2011-06-21 - Make binarydebug a parameter of DNSQuery in addition to plain debug
// D Tucny - 2011-06-21 - Add header flag parsing
// D Tucny - 2011-06-21 - Add error handling for truncation flag being set when using UDP, i.e response too large
// D Tucny - 2011-06-21 - Add decoding of SRV RRs
// D Tucny - 2011-06-22 - Add initial decoding of DNSKEY RRs
//
// D Cutting - 2012-06-22 - Added special case "." query for root-servers
// D Cutting - 2012-06-22 - Fixed . positioning bug in 0.04
//
// Remi Smith - 2013-05-23 - Alternative AAAA IPV6 Patching for 0.02 (included into general build - DC)
// D Cutting - 2013-08-04 - Implemented bugfix for Serial and TTL provided by Kim Akero
// D Cutting - 2014-02-21 - Implemented DNSKEY type recovery from data packet
// D Cutting - 2014-11-19 - Fixed fsockopen bug (BID396) thanks to semperfi on forum
// D Cutting - 2014-12-30 - Added stream timeout function (thanks to Jorgen Thomsen) [1.04]
//                          Also corrected some indentation, added comment and updated copyright
// D Cutting - 2014-12-30 - Corrected error typo (thanks to Jorgen Thomsen) [1.05]

class DNSTypes
{
	var $types_by_id;
	var $types_by_name;
	
	function AddType($id,$name)
	{
		$this->types_by_id[$id]=$name;
		$this->types_by_name[$name]=$id;
	}
	
	function DNSTypes()
	{
		$this->types_by_id=array();
		$this->types_by_name=array();
		
		$this->AddType(1,"A"); // RFC 1035 (Address Record)
		$this->AddType(2,"NS"); // RFC 1035 (Name Server Record)
		$this->AddType(5,"CNAME"); // RFC 1035 (Canonical Name Record (Alias))
		$this->AddType(6,"SOA"); // RFC 1035 (Start of Authority Record)
		$this->AddType(12,"PTR"); // RFC 1035 (Pointer Record)
		$this->AddType(15,"MX"); // RFC 1035 (Mail eXchanger Record)
		$this->AddType(16,"TXT"); // RFC 1035 (Text Record)
		$this->AddType(17,"RP"); // RFC 1183 (Responsible Person)
		$this->AddType(18,"AFSDB"); // RFC 1183 (AFS Database Record)
		$this->AddType(24,"SIG"); // RFC 2535
		$this->AddType(25,"KEY"); // RFC 2535 & RFC 2930 
		$this->AddType(28,"AAAA"); // RFC 3596 (IPv6 Address)
		$this->AddType(29,"LOC"); // RFC 1876 (Geographic Location)
		$this->AddType(33,"SRV"); // RFC 2782 (Service Locator)
		$this->AddType(35,"NAPTR"); // RFC 3403 (Naming Authority Pointer)
		$this->AddType(36,"KX"); // RFC 2230 (Key eXchanger)
		$this->AddType(37,"CERT"); // RFC 4398 (Certificate Record, PGP etc)
		$this->AddType(39,"DNAME"); // RFC 2672 (Delegation Name Record, wildcard alias)
		$this->AddType(42,"APL"); // RFC 3123 (Address Prefix List (Experimental)
		$this->AddType(43,"DS"); // RFC 4034 (Delegation Signer (DNSSEC)
		$this->AddType(44,"SSHFP"); // RFC 4255 (SSH Public Key Fingerprint)
		$this->AddType(45,"IPSECKEY"); // RFC 4025 (IPSEC Key)
		$this->AddType(46,"RRSIG"); // RFC 4034 (DNSSEC Signature)
		$this->AddType(47,"NSEC"); // RFC 4034 (Next-secure Record (DNSSEC))
		$this->AddType(48,"DNSKEY"); // RFC 4034 (DNS Key Record (DNSSEC))
		$this->AddType(49,"DHCID"); // RFC 4701 (DHCP Identifier)
		$this->AddType(50,"NSEC3"); // RFC 5155 (NSEC Record v3 (DNSSEC Extension))
		$this->AddType(51,"NSEC3PARAM"); // RFC 5155 (NSEC3 Parameters (DNSSEC Extension))
		$this->AddType(55,"HIP"); // RFC 5205 (Host Identity Protocol)
		$this->AddType(99,"SPF"); // RFC 4408 (Sender Policy Framework)
		$this->AddType(249,"TKEY"); // RFC 2930 (Secret Key)
		$this->AddType(250,"TSIG"); // RFC 2845 (Transaction Signature)
		$this->AddType(251,"IXFR"); // RFC 1995 (Incremental Zone Transfer)
		$this->AddType(252,"AXFR"); // RFC 1035 (Authoritative Zone Transfer)
		$this->AddType(255,"ANY"); // RFC 1035 AKA "*" (Pseudo Record)
		$this->AddType(32768,"TA"); // (DNSSEC Trusted Authorities)
		$this->AddType(32769,"DLV"); // RFC 4431 (DNSSEC Lookaside Validation)
	}

	function GetByName($name)
	{
		if (isset($this->types_by_name[$name])) return $this->types_by_name[$name];
		return 0;
	}
		
	function GetById($id)
	{
		if (isset($this->types_by_id[$id])) return $this->types_by_id[$id];
		return "";
	}
}

class DNSResult
{
	var $type;
	var $typeid;
	var $class;
	var $ttl;
	var $data;
	var $domain;
	var $string;
	var $extras=array();
}

class DNSAnswer
{
	var $count=0;
	var $results=array();
	
	function AddResult($type,$typeid,$class,$ttl,$data,$domain="",$string="",$extras=array())
	{
		$this->results[$this->count]=new DNSResult();
		$this->results[$this->count]->type=$type;
		$this->results[$this->count]->typeid=$typeid;
		$this->results[$this->count]->class=$class;
		$this->results[$this->count]->ttl=$ttl;
		$this->results[$this->count]->data=$data;
		$this->results[$this->count]->domain=$domain;
		$this->results[$this->count]->string=$string;
		$this->results[$this->count]->extras=$extras;
		$this->count++;
		return ($this->count-1);
	}
}
		

class DNSQuery
{
	var $server="";
	var $port;
	var $timeout; // default set in constructor
	var $udp;
	var $debug;
	var $binarydebug=false;
	
	var $types;
	
	var $rawbuffer="";
	var $rawheader="";
	var $rawresponse="";
	var $header;
	var $responsecounter=0;
	
	var $lastnameservers;
	var $lastadditional;
	
	var $error=false;
	var $lasterror="";
	
	function ReadResponse($count=1,$offset="")
	{
		if ($offset=="") // no offset so use and increment the ongoing counter
			{
			$return=substr($this->rawbuffer,$this->responsecounter,$count);
			$this->responsecounter+=$count;
			}
		else
			{
			$return=substr($this->rawbuffer,$offset,$count);
			}
		return $return;
	}
	
	function ReadDomainLabels($offset,&$counter=0)
	{
		$labels=array();
		$startoffset=$offset;
		$return=false;
		while (!$return)
			{
			$label_len=ord($this->ReadResponse(1,$offset++));
			if ($label_len<=0) $return=true; // end of data
			else if ($label_len<64) // uncompressed data
				{
				$labels[]=$this->ReadResponse($label_len,$offset);
				$offset+=$label_len;
				}
			else // label_len>=64 -- pointer
				{
				$nextitem=$this->ReadResponse(1,$offset++);
				$pointer_offset = ( ($label_len & 0x3f) << 8 ) + ord($nextitem);
				// Branch Back Upon Ourselves...
				$this->Debug("Label Offset: ".$pointer_offset);
				$pointer_labels=$this->ReadDomainLabels($pointer_offset);
				foreach($pointer_labels as $ptr_label)
					$labels[]=$ptr_label;
				$return=true;
				}
			}
		$counter=$offset-$startoffset;
		return $labels;
	}
	
	function ReadDomainLabel()
	{
		$count=0;
		$labels=$this->ReadDomainLabels($this->responsecounter,$count);
		$domain=implode(".",$labels);
		$this->responsecounter+=$count;
		$this->Debug("Label ".$domain." len ".$count);
		return $domain;
	}
	
	function Debug($text)
	{
		if ($this->debug) echo $text."\n";
	}
	
	function DebugBinary($data)
	{
		if ($this->binarydebug)
		{
			for ($a=0; $a<strlen($data); $a++)
			{
				echo $a;
				echo "\t";
				printf("%d",$data[$a]);
				echo "\t";
				$hex=bin2hex($data[$a]);
				echo "0x".$hex;
				echo "\t";
				$dec=hexdec($hex);
				echo $dec;
				echo "\t";
				if (($dec>30)&&($dec<150)) echo $data[$a];
				echo "\n";
			}
		}
	}
	
	function SetError($text)
	{
		$this->error=true;
		$this->lasterror=$text;
		$this->Debug("Error: ".$text);
	}
	
	function ClearError()
	{
		$this->error=false;
		$this->lasterror="";
	}
	
	function DNSQuery($server,$port=53,$timeout=60,$udp=true,$debug=false,$binarydebug=false)
	{
		$this->server=$server;
		$this->port=$port;
		$this->timeout=$timeout;
		$this->udp=$udp;
		$this->debug=$debug;
		$this->binarydebug=$binarydebug;
		
		$this->types=new DNSTypes();
		$this->Debug("DNSQuery Class Initialised");
	}
	
	
	function ReadRecord()
	{
		// First the pesky domain names - maybe not so pesky though I suppose
			
		$domain=$this->ReadDomainLabel(); 
			
	
			
		$ans_header_bin=$this->ReadResponse(10); // 10 byte header
		$ans_header=unpack("ntype/nclass/Nttl/nlength",$ans_header_bin);
		$this->Debug("Record Type ".$ans_header['type']." Class ".$ans_header['class']." TTL ".$ans_header['ttl']." Length ".$ans_header['length']);
	
		$typeid=$this->types->GetById($ans_header['type']);
		$extras=array();
		$data="";
		$string="";

		switch($typeid)
			{
			case "A":
				$ipbin = $this->ReadResponse(4);
				if (function_exists('inet_ntop')) {
					$ip = inet_ntop($ipbin);
				} else {
					$ip=implode(".",unpack("Ca/Cb/Cc/Cd",$ipbin));
				}
				$data=$ip;
				$extras['ipbin'] = $ipbin;
				$string=$domain." has IPv4 address ".$ip;
				break;

			case "AAAA":
				$ipbin = $this->ReadResponse(16);
				if (function_exists('inet_ntop')) {
					$ip = inet_ntop($ipbin);
				} else {
					$ip = implode(":",unpack("H4a/H4b/H4c/H4d/H4e/H4f/H4g/H4h",$ipbin));
				}
				$data = $ip;
				$extras['ipbin'] = $ipbin;
				$string = $domain . " has IPv6 address " . $ip;
				break;

			case "CNAME":
				$data=$this->ReadDomainLabel();
				$string=$domain." alias of ".$data;
				break;

			case "DNAME":
				$data=$this->ReadDomainLabel();
				$string=$domain." alias of ".$data;
				break;

			case "DNSKEY":
			case "KEY":
				$stuff = $this->ReadResponse(4);

				// key type test 21/02/2014 DC
				$test = unpack("nflags/cprotocol/calgo",$stuff);
				$extras['flags']=$test['flags'];
				$extras['protocol']=$test['protocol'];
				$extras['algorithm']=$test['algo'];

				$data = base64_encode($this->ReadResponse($ans_header['length'] - 4));
				$string = $domain." KEY ".$data;
				break;

			case "MX":
				$prefs=$this->ReadResponse(2);
				$prefs=unpack("nlevel",$prefs);
				$extras['level']=$prefs['level'];
				$data=$this->ReadDomainLabel();
				$string=$domain." mailserver ".$data." (pri=".$extras['level'].")";
				break;
				
			case "NS":
				$nameserver=$this->ReadDomainLabel();
				$data=$nameserver;
				$string=$domain." nameserver ".$nameserver;
				break;
				
			case "PTR":
				$data=$this->ReadDomainLabel();
				$string=$domain." points to ".$data;
				break;
				
			case "SOA":
				// Label First
				$data=$this->ReadDomainLabel();
				$responsible=$this->ReadDomainLabel();
				
				$buffer=$this->ReadResponse(20);
				$extras=unpack("Nserial/Nrefresh/Nretry/Nexpiry/Nminttl",$buffer); // butfix to NNNNN from nnNNN for 1.01
				$dot=strpos($responsible,".");
				$responsible[$dot]="@";
				$extras['responsible']=$responsible;
				$string=$domain." SOA ".$data." Serial ".$extras['serial'];
				break;
		
			case "SRV":
				$prefs=$this->ReadResponse(6);
				$prefs=unpack("npriority/nweight/nport",$prefs);
				$extras['priority']=$prefs['priority'];
				$extras['weight']=$prefs['weight'];
				$extras['port']=$prefs['port'];
				$data=$this->ReadDomainLabel();
				$string=$domain." SRV ".$data.":" . $extras['port'] . " (pri=".$extras['priority'].", weight=".$extras['weight'].")";
				break;
				
			case "TXT":
			case "SPF":
				$data = "";
				for ($string_count = 0; strlen($data) + (1 + $string_count) < $ans_header['length']; $string_count++) {
					$string_length = ord($this->ReadResponse(1));
					$data .= $this->ReadResponse($string_length);
				}
				
				$string=$domain." TEXT \"".$data . "\" (in " . $string_count . " strings)";
				break;

			default: // something we can't deal with
				$stuff=$this->ReadResponse($ans_header['length']);
				break;
				
			}
	
		//$dns_answer->AddResult($ans_header['type'],$typeid,$ans_header['class'],$ans_header['ttl'],$data,$domain,$string,$extras);
		$return=array(
			"header" => $ans_header,
			"typeid" => $typeid,
			"data" => $data,
			"domain" => $domain,
			"string" => $string,
			"extras" => $extras );
		return $return;
	}

		
	
	
	
	
	function Query($question,$type="A")
	{
		$this->ClearError();
		$typeid=$this->types->GetByName($type);
		if ($typeid===false)
		{
			$this->SetError("Invalid Query Type ".$type);
			return false;
		}
			
		if ($this->udp) $host="udp://".$this->server;
		else $host=$this->server;
		
		$errno=0;
		$errstr="";
		if (!$socket=fsockopen($host,$this->port,$errno,$errstr,$this->timeout))
		{
			$this->SetError("Failed to Open Socket");
			return false;
		}

		if (function_exists("stream_set_timeout")) // handles timeout on stream read set using timeout as well
 			stream_set_timeout($socket, $this->timeout);
			
		// Split Into Labels
		if (preg_match("/[a-z|A-Z]/",$question)==0 && $question!=".") // IP Address
		{
			$labeltmp=explode(".",$question);	// reverse ARPA format
			for ($i=count($labeltmp)-1; $i>=0; $i--)
				$labels[]=$labeltmp[$i];
			$labels[]="IN-ADDR";
			$labels[]="ARPA";
		}
		else if ($question == ".")
		{
			$labels=array("");
		}
		else // hostname
			$labels=explode(".",$question);

		$question_binary="";
		for ($a=0; $a<count($labels); $a++)
		{
			if ($labels[$a] != "")
			{
				$size=strlen($labels[$a]);
				$question_binary.=pack("C",$size); // size byte first
				$question_binary.=$labels[$a]; // then the label
			}
			else
			{
				$size=0;
				//$question_binary.=pack("C",$size);
				//$question_binary.=pack("C",$labels[$a]);
			}
		}
		$question_binary.=pack("C",0); // end it off
		
		$this->Debug("Question: ".$question." (type=".$type."/".$typeid.")");
		
		$id=rand(1,255)|(rand(0,255)<<8);	// generate the ID
		
		// Set standard codes and flags
		$flags=0x0100 & 0x0300; // recursion & queryspecmask
		$opcode=0x0000; // opcode
		
		// Build the header
		$header="";
		$header.=pack("n",$id);
		$header.=pack("n",$opcode | $flags);
		$header.=pack("nnnn",1,0,0,0);
		$header.=$question_binary;
		$header.=pack("n",$typeid);
		$header.=pack("n",0x0001); // internet class
		$headersize=strlen($header);
		$headersizebin=pack("n",$headersize);
		
		$this->Debug("Header Length: ".$headersize." Bytes");
		$this->DebugBinary($header);
		
		if ( ($this->udp) && ($headersize>=512) )
			{
			$this->SetError("Question too big for UDP (".$headersize." bytes)");
			fclose($socket);
			return false;
			}
			
		if ($this->udp) // UDP method
		{
			if (!fwrite($socket,$header,$headersize))
			{
				$this->SetError("Failed to write question to socket");
				fclose($socket);
				return false;
			}
			if (!$this->rawbuffer=fread($socket,4096)) // read until the end with UDP
			{
				$this->SetError("Failed to read data buffer");
				fclose($socket);
				return false;
			}				
		}
		else // TCP
		{
			if (!fwrite($socket,$headersizebin)) // write the socket
			{
				$this->SetError("Failed to write question length to TCP socket");
				fclose($socket);
				return false;
			}
			if (!fwrite($socket,$header,$headersize))
			{
				$this->SetError("Failed to write question to TCP socket");
				fclose($socket);
				return false;
			}
			if (!$returnsize=fread($socket,2))
			{
				$this->SetError("Failed to read size from TCP socket");
				fclose($socket);
				return false;
			}
			$tmplen=unpack("nlength",$returnsize);
			$datasize=$tmplen['length'];
			$this->Debug("TCP Stream Length Limit ".$datasize);
			if (!$this->rawbuffer=fread($socket,$datasize))
			{
				$this->SetError("Failed to read data buffer");
				fclose($socket);
				return false;
			}
		}
		fclose($socket);
		
		$buffersize=strlen($this->rawbuffer);
		$this->Debug("Read Buffer Size ".$buffersize);
		
		if ($buffersize<12)
		{
			$this->SetError("Return Buffer too Small");
			return false;
		}
			
		$this->rawheader=substr($this->rawbuffer,0,12); // first 12 bytes is the header
		$this->rawresponse=substr($this->rawbuffer,12); // after that the response
		
		$this->responsecounter=12; // start parsing response counter from 12 - no longer using response so can do pointers
		
		$this->DebugBinary($this->rawbuffer);
		
		$this->header=unpack("nid/nspec/nqdcount/nancount/nnscount/narcount",$this->rawheader);

		$id = $this->header['id'];

		$rcode = $this->header['spec'] & 15;
		$z = ($this->header['spec'] >> 4) & 7;
		$ra = ($this->header['spec'] >> 7) & 1;
		$rd = ($this->header['spec'] >> 8) & 1;
		$tc = ($this->header['spec'] >> 9) & 1;
		$aa = ($this->header['spec'] >> 10) & 1;
		$opcode = ($this->header['spec'] >> 11) & 15;
		$type = ($this->header['spec'] >> 15) & 1;

		$this->Debug("ID=$id, Type=$type, OPCODE=$opcode, AA=$aa, TC=$tc, RD=$rd, RA=$ra, RCODE=$rcode");

		if ($tc==1 && $this->udp) { // Truncation detected
			$this->SetError("Response too big for UDP, retry with TCP");
			return false;
		}
		
		$answers=$this->header['ancount'];
		
		$this->Debug("Query Returned ".$answers." Answers");
		
		$dns_answer=new DNSAnswer();
		
		// Deal with the header question data
		if ($this->header['qdcount']>0)
			{
			$this->Debug("Found ".$this->header['qdcount']." Questions");
			for ($a=0; $a<$this->header['qdcount']; $a++)
				{
				$c=1;
				while ($c!=0)
					{
					$c=hexdec(bin2hex($this->ReadResponse(1)));
					}
				$extradata=$this->ReadResponse(4);
				}
			}

		// New Functional Method
		for ($a=0; $a<$this->header['ancount']; $a++)
			{
			$record=$this->ReadRecord();
			$dns_answer->AddResult($record['header']['type'],$record['typeid'],$record['header']['class'],$record['header']['ttl'],
				$record['data'],$record['domain'],$record['string'],$record['extras']);
			}
			
		$this->lastnameservers=new DNSAnswer();
		for ($a=0; $a<$this->header['nscount']; $a++)
			{
			$record=$this->ReadRecord();
			$this->lastnameservers->AddResult($record['header']['type'],$record['typeid'],$record['header']['class'],$record['header']['ttl'],
				$record['data'],$record['domain'],$record['string'],$record['extras']);
			}
			
		$this->lastadditional=new DNSAnswer();
		for ($a=0; $a<$this->header['arcount']; $a++)
			{
			$record=$this->ReadRecord();
			$this->lastadditional->AddResult($record['header']['type'],$record['typeid'],$record['header']['class'],$record['header']['ttl'],
				$record['data'],$record['domain'],$record['string'],$record['extras']);
			}
			

				
		
	return $dns_answer; 
	}
	
	
function SmartALookup($hostname,$depth=0)
	{
	$this->Debug("SmartALookup for ".$hostname." depth ".$depth);
	if ($depth>5) return ""; // avoid recursive lookups
	// The SmartALookup function will resolve CNAMES using the additional properties if possible
	$answer=$this->Query($hostname,"A");
	
	if ($answer===false) return "";		// failed totally
	if ($answer->count<=0) return "";	// no records at all returned
	
	$best_answer="";
	$best_answer_typeid=0;
	
	$records=$answer->count;
	for ($a=0; $a<$records; $a++)
		{
		$data=$answer->results[$a]->data;
		$answer_typeid=$answer->results[$a]->typeid;
		
		if ($answer_typeid=="A") // found it
			{
			$best_answer=$data;
			$best_answer_typeid="A";
			$a=$records+10;
			}
		else if ($answer_typeid=="CNAME") // alias
			{
			$best_answer=$data;
			$best_answer_typeid="CNAME";
			// and keep going
			}
			
		}
		
	if ( ($best_answer=="") || ($best_answer_typeid=="") ) return "";
	
	if ($best_answer_typeid=="A") return $best_answer; // got an IP ok
	
	if ($best_answer_typeid!="CNAME") return ""; // shouldn't ever happen
	
	$newtarget=$best_answer; // this is what we now need to resolve
	
	// First is it in the additional section
	for ($a=0; $a<$this->lastadditional->count; $a++)
		{
		if ( ($this->lastadditional->results[$a]->domain==$hostname) &&
			($this->lastadditional->results[$a]->typeid=="A") )
				return $this->lastadditional->results[$a]->data;
		}
		
	// Not in the results
	
	return $this->SmartALookup($newtarget,++$depth);
	}
		
}

if (!function_exists('inet_ntop')) {
	function inet_ntop($ip) {
		if (strlen($ip)==4) {
			// ipv4
			list(,$ip)=unpack('N',$ip);
			$ip=long2ip($ip);
		} elseif(strlen($ip)==16) {
			// ipv6
			$ip=bin2hex($ip);
			$ip=substr(chunk_split($ip,4,':'),0,-1);
			$ip=explode(':',$ip);
			$res='';
			foreach($ip as $seg) {
				while($seg{0}=='0') $seg=substr($seg,1);
				if ($seg!='') {
					$res.=($res==''?'':':').$seg;
				} else {
					if (strpos($res,'::')===false) {
						if (substr($res,-1)==':') continue;
						$res.=':';
						continue;
					}
					$res.=($res==''?'':':').'0';
				}
			}
			$ip=$res;
		}
		return $ip;
	}
}

?>
