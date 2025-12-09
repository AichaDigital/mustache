<?php

declare(strict_types=1);

use AichaDigital\MustacheResolver\Core\Token\TokenClassifier;
use AichaDigital\MustacheResolver\Core\Token\TokenType;

describe('TokenClassifier', function () {
    beforeEach(function () {
        $this->classifier = new TokenClassifier;
    });

    describe('Model tokens', function () {
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

    describe('Table tokens', function () {
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

    describe('Relation tokens', function () {
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

    describe('Dynamic field tokens', function () {
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

    describe('Collection tokens', function () {
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

    describe('Function tokens', function () {
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

    describe('Variable tokens', function () {
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

    describe('Null coalesce tokens', function () {
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

    describe('Math tokens', function () {
        it('classifies addition expression', function () {
            $token = $this->classifier->classify('10 + 5');

            expect($token->getType())->toBe(TokenType::MATH);
        });

        it('classifies complex math expression', function () {
            $token = $this->classifier->classify('(10 + 5) * 2');

            expect($token->getType())->toBe(TokenType::MATH);
        });
    });
});
