<?php

declare(strict_types=1);

namespace DesignQa\Tests\Unit\Infrastructure\Secrets;

use DesignQa\Infrastructure\Secrets\ChainTokenStore;
use DesignQa\Infrastructure\Secrets\EnvTokenStore;
use DesignQa\Infrastructure\Secrets\KeychainTokenStore;
use DesignQa\Infrastructure\Secrets\MemoizingTokenStore;
use DesignQa\Infrastructure\System\CommandResult;
use DesignQa\Tests\Support\FakeCommandRunner;
use DesignQa\Tests\Support\InMemoryTokenStore;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(KeychainTokenStore::class)]
#[CoversClass(EnvTokenStore::class)]
#[CoversClass(ChainTokenStore::class)]
#[CoversClass(MemoizingTokenStore::class)]
final class TokenStoresTest extends TestCase
{
    public function testKeychainReadsTokenWithSecurityTool(): void
    {
        $runner = new FakeCommandRunner(new CommandResult(0, "figd_abc\n"));

        $secret = (new KeychainTokenStore($runner))->fetch();

        self::assertSame('figd_abc', $secret?->reveal());
        self::assertSame(
            [['security', 'find-generic-password', '-a', 'design-qa', '-s', 'design-qa-figma', '-w']],
            $runner->commands,
        );
    }

    public function testKeychainReturnsNullWhenItemIsMissing(): void
    {
        $runner = new FakeCommandRunner(new CommandResult(44, '', 'The specified item could not be found in the keychain.'));

        self::assertNull((new KeychainTokenStore($runner))->fetch());
    }

    public function testKeychainSaveLetsSecurityPromptSoTheTokenIsNeverAnArgument(): void
    {
        $runner = new FakeCommandRunner();

        self::assertTrue((new KeychainTokenStore($runner))->promptAndSave());
        self::assertSame(
            [['security', 'add-generic-password', '-U', '-a', 'design-qa', '-s', 'design-qa-figma', '-w']],
            $runner->interactiveCommands,
        );
    }

    public function testEnvStoreReadsVariable(): void
    {
        self::assertSame('figd_env', (new EnvTokenStore(['FIGMA_TOKEN' => 'figd_env']))->fetch()?->reveal());
        self::assertNull((new EnvTokenStore(['FIGMA_TOKEN' => '']))->fetch());
        self::assertNull((new EnvTokenStore([]))->fetch());
    }

    public function testMemoizingStoreReadsTheKeychainOnce(): void
    {
        $inner = new InMemoryTokenStore('figd_once', 'keychain');
        $store = new MemoizingTokenStore($inner);

        self::assertSame('figd_once', $store->fetch()?->reveal());
        $store->fetch();
        self::assertSame(1, $inner->fetches);
        self::assertSame('keychain', $store->describe());
    }

    public function testChainUsesFirstStoreWithATokenAndNamesIt(): void
    {
        $empty = new InMemoryTokenStore(null, 'env');
        $keychain = new InMemoryTokenStore('figd_keychain', 'keychain');
        $never = new InMemoryTokenStore('figd_other', 'other');
        $chain = new ChainTokenStore($empty, $keychain, $never);

        self::assertSame('env or keychain or other', $chain->describe());
        self::assertSame('figd_keychain', $chain->fetch()?->reveal());
        self::assertSame('keychain', $chain->describe());
        self::assertSame(0, $never->fetches);
    }
}
