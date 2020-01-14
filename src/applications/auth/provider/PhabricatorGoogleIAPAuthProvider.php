<?php
// TM CHANGES BEGIN: Entire class is added.

use SimpleJWT\JWT;
use SimpleJWT\Keys\KeySet;
use SimpleJWT\InvalidTokenException;

// Auth provider for Google Cloud Identity-Aware Proxy.
// It assumes that Phabricator is accessed through the proxy and so its task is to
// verify the signed JWTs and sign the user in.
final class PhabricatorGoogleIAPAuthProvider
  extends PhabricatorAuthProvider {

  const IAP_HEADER = 'x-goog-iap-jwt-assertion';

  protected $adapter;

  /**
   * The public keys we will download from Google to verify signatures with.
   * @var KeySet
   */
  private $keys;

  public function getProviderName() {
    return pht('Google Cloud IAP');
  }

  public function getDescriptionForCreate() {
    return pht('Allow users to log in through Google Cloud Identity-Aware Proxy.');
  }

  protected function getProviderConfigurationHelp() {
    return pht("To configure Google Cloud IAP, use the Google Cloud Console.");
  }

  public function getAdapter() {
    if (!$this->adapter) {
      $adapter = new PhutilEmptyAuthAdapter();
      $adapter->setAdapterType('proxy');
      $adapter->setAdapterDomain('google.com');
      $this->adapter = $adapter;
    }
    return $this->adapter;
  }

  protected function renderLoginForm(AphrontRequest $request, $mode) {
    return null;
  }

  public function processLoginRequest(PhabricatorAuthLoginController $controller) {
    $account = null;
    $response = null;
    try {
      $request = $controller->getRequest();
      $email = $this->verifyJWT($request->getHTTPHeader(
          PhabricatorGoogleIAPAuthProvider::IAP_HEADER));
      return array($this->loadOrCreateAccount($email), $response);
    } catch (Exception $ex) {
      $response = $controller->buildProviderErrorResponse($this, $ex->getMessage());
      return array($account, $response);
    }
  }

  // Verifies the signed JWT header. Returns the email address on success.
  public function verifyJWT($header) {
    if (!$header) {
      throw new Exception('Missing JWT header in request');
    }
    if (!$this->keys) {
      $this->loadKeys();
    }
    try {
      $jwt = JWT::decode($header, $this->keys, 'ES256');
    } catch (InvalidTokenException $e) {
      throw new Exception('Failed to validate JWT: ' . $e->getMessage());
    }
    if ($jwt->getClaim('iss') != 'https://cloud.google.com/iap') {
      throw new Exception('Invalid issuer for JWT');
    }
    return $jwt->getClaim('email');
  }

  // Downloads the public keys that we'll verify the JWT with.
  private function loadKeys() {
    $uri = new PhutilURI('https://www.gstatic.com/iap/verify/public_key-jwk');
    $future = new HTTPSFuture($uri);
    list($status, $body) = $future->resolve();
    if ($status->isError()) {
      throw $status;
    }
    $keyset = new KeySet();
    $keyset->load($body);
    $this->keys = $keyset;
  }

  protected function getLoginIcon() {
    return 'Google';
  }

  public function canAuthRequest(AphrontRequest $request) {
    return (bool) $request->getHTTPHeader(PhabricatorGoogleIAPAuthProvider::IAP_HEADER);
  }

}
// TM CHANGES END
