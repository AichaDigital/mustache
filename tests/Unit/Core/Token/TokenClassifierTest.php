<?php

declare(strict_types=1);

use AichaDigital\MustacheResolver\Core\Token\TokenClassifier;
use AichaDigital\MustacheResolver\Core\Token\TokenType;

beforeEach(function () {
    $this->classifier = new TokenClassifier;
});

describe('TokenClassifier → Model tokens', function () {
    it('classifies simple model field access', function () {
        $token = $this->classifier->classify('User.name');

        expect($token->getType())->toBe(TokenType::MODEL);
        expect($token->getPrefix())->toBe('User');
        expect($token->getFieldPath())->toBe(['name']);
    });

    it('classifies model with PascalCase prefix', function () {
        $token = $this->classifier->classify('CommandCenter.status');

        expect($token->getType())->toBe(TokenType::MODEL);
        expect($token->getPrefix())->toBe('CommandCenter');
    });
});

describe('TokenClassifier → Table tokens', function () {
    it('classifies snake_case prefix as table', function () {
        $token = $this->classifier->classify('users.email');

        expect($token->getType())->toBe(TokenType::TABLE);
        expect($token->getPrefix())->toBe('users');
    });

    it('classifies multi-word snake_case table', function () {
        $token = $this->classifier->classify('user_profiles.avatar');

        expect($token->getType())->toBe(TokenType::TABLE);
    });
});

describe('TokenClassifier → Relation tokens', function () {
    it('classifies deep relation chains', function () {
        $token = $this->classifier->classify('User.department.manager.name');

        expect($token->getType())->toBe(TokenType::RELATION);
        expect($token->getPath())->toBe(['User', 'department', 'manager', 'name']);
    });

    it('classifies three-level relation', function () {
        $token = $this->classifier->classify('Order.customer.address');

        expect($token->getType())->toBe(TokenType::RELATION);
    });
});

describe('TokenClassifier → Dynamic field tokens', function () {
    it('classifies dynamic field with $ prefix', function () {
        $token = $this->classifier->classify('Device.$manufacturer.field_parameter');

        expect($token->getType())->toBe(TokenType::DYNAMIC);
        expect($token->isDynamic())->toBeTrue();
    });

    it('identifies dynamic in middle of path', function () {
        $token = $this->classifier->classify('User.settings.$config.value');

        expect($token->getType())->toBe(TokenType::DYNAMIC);
    });
});

describe('TokenClassifier → Collection tokens', function () {
    it('classifies numeric index access', function () {
        $token = $this->classifier->classify('User.posts.0.title');

        expect($token->getType())->toBe(TokenType::COLLECTION);
    });

    it('classifies first keyword', function () {
        $token = $this->classifier->classify('User.addresses.first.city');

        expect($token->getType())->toBe(TokenType::COLLECTION);
    });

    it('classifies last keyword', function () {
        $token = $this->classifier->classify('User.posts.last.title');

        expect($token->getType())->toBe(TokenType::COLLECTION);
    });

    it('classifies wildcard access', function () {
        $token = $this->classifier->classify('User.posts.*.title');

        expect($token->getType())->toBe(TokenType::COLLECTION);
    });
});

describe('TokenClassifier → Function tokens', function () {
    it('classifies simple function call', function () {
        $token = $this->classifier->classify('now()');

        expect($token->getType())->toBe(TokenType::FUNCTION);
        expect($token->getFunctionName())->toBe('now');
        expect($token->getFunctionArgs())->toBe([]);
    });

    it('classifies function with string argument', function () {
        $token = $this->classifier->classify("now('Y-m-d')");

        expect($token->getType())->toBe(TokenType::FUNCTION);
        expect($token->getFunctionName())->toBe('now');
        expect($token->getFunctionArgs())->toBe(['Y-m-d']);
    });

    it('classifies function with multiple arguments', function () {
        $token = $this->classifier->classify("format(User.date, 'Y-m-d', true)");

        expect($token->getType())->toBe(TokenType::FUNCTION);
        expect($token->getFunctionName())->toBe('format');
        expect($token->getFunctionArgs())->toBe(['User.date', 'Y-m-d', true]);
    });

    it('parses numeric arguments correctly', function () {
        $token = $this->classifier->classify('add(10, 5.5)');

        expect($token->getFunctionArgs())->toBe([10, 5.5]);
    });

    it('parses boolean arguments', function () {
        $token = $this->classifier->classify('toggle(true, false)');

        expect($token->getFunctionArgs())->toBe([true, false]);
    });

    it('parses null argument', function () {
        $token = $this->classifier->classify('optional(null)');

        expect($token->getFunctionArgs())->toBe([null]);
    });
});

describe('TokenClassifier → Variable tokens', function () {
    it('classifies variable reference', function () {
        $token = $this->classifier->classify('$myVariable');

        expect($token->getType())->toBe(TokenType::VARIABLE);
        expect($token->getPath())->toBe(['myVariable']);
    });

    it('classifies camelCase variable', function () {
        $token = $this->classifier->classify('$currentUser');

        expect($token->getType())->toBe(TokenType::VARIABLE);
    });
});

describe('TokenClassifier → Null coalesce tokens', function () {
    it('classifies null coalesce expression', function () {
        $token = $this->classifier->classify("User.name ?? 'default'");

        expect($token->getType())->toBe(TokenType::NULL_COALESCE);
        expect($token->getDefaultValue())->toBe('default');
    });

    it('extracts path before null coalesce', function () {
        $token = $this->classifier->classify("User.nickname ?? 'Anonymous'");

        expect($token->getPath())->toBe(['User', 'nickname']);
        expect($token->getDefaultValue())->toBe('Anonymous');
    });
});

describe('TokenClassifier → Math tokens', function () {
    it('classifies addition expression', function () {
        $token = $this->classifier->classify('10 + 5');

        expect($token->getType())->toBe(TokenType::MATH);
    });

    it('classifies complex math expression', function () {
        $token = $this->classifier->classify('(10 + 5) * 2');

        expect($token->getType())->toBe(TokenType::MATH);
    });
});

describe('TokenClassifier → Temporal tokens', function () {
    it('classifies TEMPORAL:isDue with single expression argument', function () {
        $token = $this->classifier->classify("TEMPORAL:isDue('weekday && 08:00-18:00')");

        expect($token->getType())->toBe(TokenType::TEMPORAL);
        expect($token->getFunctionName())->toBe('isDue');
        expect($token->getFunctionArgs())->toBe(['weekday && 08:00-18:00']);
    });

    it('classifies TEMPORAL:isNthWeekday with multiple arguments', function () {
        $token = $this->classifier->classify("TEMPORAL:isNthWeekday('saturday', 1)");

        expect($token->getType())->toBe(TokenType::TEMPORAL);
        expect($token->getFunctionName())->toBe('isNthWeekday');
        expect($token->getFunctionArgs())->toBe(['saturday', 1]);
    });

    it('classifies TEMPORAL:isNthWeekday with second occurrence', function () {
        $token = $this->classifier->classify("TEMPORAL:isNthWeekday('friday', 2)");

        expect($token->getType())->toBe(TokenType::TEMPORAL);
        expect($token->getFunctionName())->toBe('isNthWeekday');
        expect($token->getFunctionArgs())->toBe(['friday', 2]);
    });

    it('classifies TEMPORAL:isLastWeekday with single argument', function () {
        $token = $this->classifier->classify("TEMPORAL:isLastWeekday('saturday')");

        expect($token->getType())->toBe(TokenType::TEMPORAL);
        expect($token->getFunctionName())->toBe('isLastWeekday');
        expect($token->getFunctionArgs())->toBe(['saturday']);
    });

    it('classifies NOW', function () {
        $token = $this->classifier->classify('NOW');

        expect($token->getType())->toBe(TokenType::TEMPORAL);
        expect($token->getFunctionName())->toBe('default');
    });

    it('classifies NOW:format with argument', function () {
        $token = $this->classifier->classify("NOW:format('Y-m-d')");

        expect($token->getType())->toBe(TokenType::TEMPORAL);
        expect($token->getFunctionName())->toBe('format');
        expect($token->getFunctionArgs())->toBe(['Y-m-d']);
    });

    it('classifies NOW:timestamp', function () {
        $token = $this->classifier->classify('NOW:timestamp');

        expect($token->getType())->toBe(TokenType::TEMPORAL);
        expect($token->getFunctionName())->toBe('timestamp');
    });

    it('classifies TODAY', function () {
        $token = $this->classifier->classify('TODAY');

        expect($token->getType())->toBe(TokenType::TEMPORAL);
        expect($token->getFunctionName())->toBe('default');
    });

    it('classifies TODAY:startOfDay', function () {
        $token = $this->classifier->classify('TODAY:startOfDay');

        expect($token->getType())->toBe(TokenType::TEMPORAL);
        expect($token->getFunctionName())->toBe('startOfDay');
    });

    it('classifies TODAY:format with argument', function () {
        $token = $this->classifier->classify("TODAY:format('d/m/Y')");

        expect($token->getType())->toBe(TokenType::TEMPORAL);
        expect($token->getFunctionName())->toBe('format');
        expect($token->getFunctionArgs())->toBe(['d/m/Y']);
    });

    it('classifies TEMPORAL:nextRun with cron argument', function () {
        $token = $this->classifier->classify("TEMPORAL:nextRun('cron:0 8 * * *')");

        expect($token->getType())->toBe(TokenType::TEMPORAL);
        expect($token->getFunctionName())->toBe('nextRun');
        expect($token->getFunctionArgs())->toBe(['cron:0 8 * * *']);
    });

    it('stores temporal type in metadata', function () {
        $temporal = $this->classifier->classify("TEMPORAL:isDue('weekday')");
        $now = $this->classifier->classify('NOW');
        $today = $this->classifier->classify('TODAY');

        expect($temporal->getMetadata()['temporal_type'])->toBe('temporal');
        expect($now->getMetadata()['temporal_type'])->toBe('now');
        expect($today->getMetadata()['temporal_type'])->toBe('today');
    });
});
