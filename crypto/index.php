<?php
	require 'vendor/autoload.php';
	
use Elliptic\EC;

$ec = new EC('curve25519');

// Generate keys
$key1 = $ec->genKeyPair();
$key2 = $ec->genKeyPair();

$shared1 = $key1->derive($key2->getPublic());
$shared2 = $key2->derive($key1->getPublic());

echo "Both shared secrets are BN instances\n";
echo $shared1->toString(16) . "\n";
echo $shared2->toString(16) . "\n";