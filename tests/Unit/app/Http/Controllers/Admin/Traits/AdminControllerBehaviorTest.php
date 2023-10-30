<?php

namespace Tests\Unit\app\Http\Controllers\Admin\Traits;

use App\Http\Controllers\Admin\Traits\AdminControllerBehavior;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Contracts\Encryption\EncryptException;
use Illuminate\Encryption\Encrypter;
use Illuminate\Http\Request;
use Mockery\MockInterface;
use ReflectionException;
use Symfony\Component\HttpFoundation\ParameterBag;
use Tests\TestCase;
use Tests\TestHelper;

/**
 * Class AdminControllerBehaviorTest adds code coverage for {@see AdminControllerBehavior}
 *
 * @package Tests\Unit\app\Http\Controllers\Admin\Traits
 * @coversDefaultClass \App\Http\Controllers\Admin\Traits\AdminControllerBehavior
 */
class AdminControllerBehaviorTest extends TestCase
{
    /**
     * @var array|string[] containing request data parameters {name&email}
     */
    public const TEST_REQUEST_DATA = [
        'name'  => 'John',
        'email' => 'john.doe@gmail.com',
    ];

    /**
     * @var MockInterface|AdminControllerBehavior mocked instance of the tested class
     */
    private MockInterface $currentMock;

    /**
     * @var Encrypter instance for encrypt/decrypt data
     */
    private Encrypter $encrypter;

    /**
     * Setup test dependencies
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->currentMock = $this->mock(AdminControllerBehavior::class);
        $this->encrypter = new Encrypter(config('app.admin_secret_key'), config('app.cipher'));
    }

    /**
     * @test
     * that decrypt decrypts and returns the provided data string
     *
     * @covers \App\Http\Controllers\Admin\Traits\AdminControllerBehavior::decrypt
     *
     * @throws ReflectionException if decrypt method is not defined
     * @throws EncryptException if encryption fails
     */
    public function decrypt_always_decryptsData()
    {
        $encryptedData = $this->encrypter->encrypt(self::TEST_REQUEST_DATA);

        $result = TestHelper::callNonPublicFunction($this->currentMock, 'decrypt', [$encryptedData]);

        $this->assertEquals(self::TEST_REQUEST_DATA, $result);
    }

    /**
     * @test
     * that encrypt encrypts and returns the provided data string
     *
     * @covers \App\Http\Controllers\Admin\Traits\AdminControllerBehavior::encrypt
     *
     * @throws DecryptException If data could not be decrypted
     */
    public function encrypt_always_encryptsData()
    {
        $response = $this->currentMock->encrypt(self::TEST_REQUEST_DATA);

        $this->assertNotEquals(self::TEST_REQUEST_DATA, $response);

        $decryptedResponse = $this->encrypter->decrypt($response);

        $this->assertEquals(self::TEST_REQUEST_DATA, json_decode($decryptedResponse, true));
    }

    /**
     * @test
     * that getDecryptedRequest returns {@see ParameterBag} instance containing decrypted request data
     *
     * @covers \App\Http\Controllers\Admin\Traits\AdminControllerBehavior::getDecryptedRequest
     *
     * @throws ReflectionException if getDecryptedRequest method is not defined
     * @throws EncryptException if encryption fails
     */
    public function getDecryptedRequest_always_returnsParameterBagInstance()
    {
        $requestMock = $this->mock(Request::class);
        $encryptedData = $this->encrypter->encrypt(json_encode(self::TEST_REQUEST_DATA));
        $requestMock->shouldReceive('getContent')->andReturn($encryptedData);

        $result = TestHelper::callNonPublicFunction(
            $this->currentMock,
            'getDecryptedRequest',
            [$requestMock]
        );

        $this->assertInstanceOf(ParameterBag::class, $result);
        $this->assertEquals(new ParameterBag(self::TEST_REQUEST_DATA), $result);
    }

    /**
     * @test
     * that getEncryptor returns {@see Encrypter} instance
     *
     * @covers \App\Http\Controllers\Admin\Traits\AdminControllerBehavior::getEncryptor
     *
     * @throws ReflectionException if getEncryptor method is not defined
     */
    public function getEncryptor_always_returnsEncryptorInstance()
    {
        $result = TestHelper::callNonPublicFunction($this->currentMock, 'getEncryptor');

        $this->assertInstanceOf(Encrypter::class, $result);
    }
}
