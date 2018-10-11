Options
#######

Resolve structured arrays (e.g. configuration) according to the specified set of options.

.. image:: https://travis-ci.com/kuria/options.svg?branch=master
   :target: https://travis-ci.com/kuria/options

.. contents::
   :depth: 4


Features
********

- type validation
- typed lists
- nullable options
- choices
- default values
- lazy defaults (that may depend on other options)
- custom validators and normalizers
- nested options (multi-dimensional arrays)
- custom resolver context


Requirements
************

- PHP 7.1+


Usage
*****

Resolving options
=================

Use ``Resolver`` to resolve arrays according to the specified options.

The ``resolve()`` method returns an instance of ``Node``, which can be
accessed as an array. See `Working with Node instances`_.

If the passed value is invalid, ``ResolverException`` will be thrown.
See `Handling validation errors`_.

.. code:: php

   <?php

   use Kuria\Options\Resolver;
   use Kuria\Options\Option;

   // create a resolver
   $resolver = new Resolver();

   // define options
   $resolver->addOption(
       Option::string('path'),
       Option::int('interval')->default(null)
   );

   // resolve an array
   $node = $resolver->resolve([
      'path' => 'file.txt',
   ]);

   var_dump($node['path'], $node['interval']);

Output:

::

  string(8) "file.txt"
  NULL


Working with ``Node`` instances
-------------------------------

By default, ``Resolver->resolve()`` returns a ``Node`` instance with the resolved options.

- ``Node`` implements ``ArrayAccess``, so the individual options can be acessed
  using array syntax: ``$node['option']``

- `lazy default values <Lazy default values (leaf-only)_>`_ are resolved once that
  option is read (or when ``toArray()`` is called)

- nested `node options <Node options_>`_ are also returned as ``Node`` instances

  (if you need to work exclusively with arrays, use ``$node->toArray()``)


Resolver context
----------------

``Resolver->resolve()`` accepts a second argument, which may be an array of additional arguments
to pass to all validators and normalizers. The values may be of any type.

.. code:: php

   <?php

   use Kuria\Options\Option;
   use Kuria\Options\Resolver;

   $resolver = new Resolver();

   $resolver->addOption(
       Option::string('option')
           ->normalize(function (string $value, $foo, $bar) {
               echo 'NORMALIZE: ';
               var_dump(func_get_args());

               return $value;
           })
           ->validate(function (string $value, $foo, $bar) {
               echo 'VALIDATE: ';
               var_dump(func_get_args());
           })
   );

   $node = $resolver->resolve(
       ['option' => 'value'],
       ['context argument 1', 'context argument 2']
   );

Output:

::

  NORMALIZE: array(3) {
    [0] =>
    string(5) "value"
    [1] =>
    string(18) "context argument 1"
    [2] =>
    string(18) "context argument 2"
  }
  VALIDATE: array(3) {
    [0] =>
    string(5) "value"
    [1] =>
    string(18) "context argument 1"
    [2] =>
    string(18) "context argument 2"
  }


Defining options
================

Terminology
-----------

.. _opt_terms:

leaf option
  An option in the option tree that does not contain children.

node option
  An option defined via ``Option::node()`` or ``Option::nodeList()``.
  They are branches in the option tree.

child option
  Any option nested inside a node option. It can be either leaf or a node option.


Option factories
----------------

The ``Option`` class provides a number of static factories to create option instances.

========================================== ===================================================
Factory                                    Description
========================================== ===================================================
``Option::any($name)``                     Mixed option that accepts all value types.
                                           ``NULL`` is accepted only if the option is nullable.

``Option::bool($name)``                    Boolean option.

``Option::int($name)``                     Integer option.

``Option::float($name)``                   Float option.

``Option::number($name)``                  Number option that accepts integers and floats.

``Option::numeric($name)``                 Numeric option that accepts integers, floats
                                           and numeric strings.

``Option::string($name)``                  String option.

``Option::array($name)``                   Array option. The individual values are not validated.

``Option::list($name, $type)``             List option that accepts an array with values of the
                                           specified type. Each value is validated and must not
                                           be ``NULL``. See `Supported types`_.

``Option::iterable($name)``                Iterable option that accepts both arrays and ``Traversable``
                                           instances. The individual values are not validated.

``Option::object($name)``                  Object option.

``Option::object($name, $className)``      Object option that only accepts instances of the given
                                           class or interface (or their descendants).

``Option::resource($name)``                Resource option.

``Option::scalar($name)``                  Scalar option that accepts integers, floats, strings
                                           and booleans.

``Option::choice($name, ...$choices)``     Choice option that accepts one of the listed values only
                                           (compared in strict mode).

``Option::choiceList($name, ...$choices)`` Choice list option that accepts an array consisting of
                                           any of the listed values (compared in strict mode).
                                           Duplicates are allowed. ``NULL`` values are not allowed.

``Option::node($name, ...$options)``       Node option that accepts an array of the specified options.
                                           See `Node options`_.

``Option::nodeList($name, ...$options)``   Node list option that accepts an array of arrays of the
                                           specified options. See `Node options`_.
========================================== ===================================================


Option configuration
--------------------

Option instances can be configured further by using the following methods.

All methods implement a fluent interface, for example:

.. code:: php

   <?php

   use Kuria\Options\Option;

   Option::string('name')
      ->default('foo')
      ->nullable();


``required()``
^^^^^^^^^^^^^^

Makes the option required (and removes any previously set default value).

- `a leaf option <opt_terms_>`_ is required by default

- `a node option <opt_terms_>`_ is not required by default, but having
  a required `child option <opt_terms_>`_ will make it required
  (unless the node option itself defaults to ``NULL``).


``default($default)``
^^^^^^^^^^^^^^^^^^^^^

Makes the option optional and specifies a default value.

- specifying ``NULL`` as the default value also makes the option nullable

- default value of `a leaf option <opt_terms_>`_ is not subject to validation
  or normalization and is used as-is

- default value of `a node option <opt_terms_>`_ must be an array or ``NULL``
  and is validated and normalized according to the specified `child options <opt_terms_>`_


Lazy default values (leaf-only)
"""""""""""""""""""""""""""""""

To specify a lazy default value, pass a closure with the following signature:

.. code:: php

   <?php

   use Kuria\Options\Node;
   use Kuria\Options\Option;

   Option::string('foo')->default(function (Node $node) {
       // return value can also depend on other options
       return 'default';
   });

Once the default value is needed, the closure will be called and its return
value stored for later use (so it will not be called more than once).

.. NOTE::

   The typehinted ``Node`` parameter is required. A closure with incompatible
   signature will be considered a default value itself and returned as-is.

.. NOTE::

   `Node options <opt_terms_>`_ do not support lazy default values.


``nullable()``
^^^^^^^^^^^^^^

Make the option nullable, accepting ``NULL`` in addition to the specified type.


``notNullable()``
^^^^^^^^^^^^^^^^^

Make the option non-nullable, not accepting ``NULL``.

.. NOTE::

   Options are non-nullable by default.


``allowEmpty()``
^^^^^^^^^^^^^^^^

Allow empty values to be passed to this option.

.. NOTE::

   Options accept empty values by default.


``notEmpty()``
^^^^^^^^^^^^^^

Make the option reject empty values.

A value is considered empty if `PHP's empty() <http://php.net/manual/en/function.empty.php>`_
returns ``TRUE``.


``normalize(callable $normalizer)``
^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^

Append a normalizer to the option. The normalizer should accept a value
and return the normalized value or throw ``Kuria\Options\Exception\NormalizerException``
on failure.

See `Normalizer and validator value types`_.

- normalizers are called before validators defined by ``validate()``
- normalizers are called in the order they were appended
- normalizers are not called if the type of the value is not valid
- the order in which options are normalized is undefined
  (but `node options <opt_terms_>`_ are normalized in child-first order)

.. code:: php

   <?php

   use Kuria\Options\Resolver;
   use Kuria\Options\Option;

   $resolver = new Resolver();

   $resolver->addOption(
       Option::string('name')->normalize('trim')
   );

   var_dump($resolver->resolve(['name' => '  foo bar  ']));


Output:

::

  object(Kuria\Options\Node)#7 (1) {
    ["name"]=>
    string(7) "foo bar"
  }

.. NOTE::

   To normalize all options at the root level, define one or more normalizers
   using ``$resolver->addNormalizer()``.

.. TIP::

   It is possible to use normalizers to convert nodes into custom objects,
   so you don't have to work with anonymous ``Node`` objects.

.. TIP::

   It is possible to pass additional arguments to all normalizers and validators.
   See `Resolver context`_.


``validate(callable $validator)``
^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^

Append a validator to the option. The validator should accept and validate a value.

- validators are called after normalizers defined by ``normalize()``
- validators are called in the order they were appended
- validators are not called if the type of the value is not valid
  or its normalization has failed
- if a validator returns one or more errors, no other validators of that option
  will be called
- the order in which options are validated is undefined
  (but `node options <opt_terms_>`_ are validated in child-first order)

The validator should return one of the following:

- ``NULL`` or an empty array if there no errors
- errors as a ``string``, an array of strings or Error instances

.. code:: php

   <?php

   use Kuria\Options\Exception\ResolverException;
   use Kuria\Options\Resolver;
   use Kuria\Options\Option;

   $resolver = new Resolver();

   $resolver->addOption(
      Option::string('email')->validate(function (string $email) {
          if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
              return 'must be a valid email address';
          }
      })
   );

   try {
       var_dump($resolver->resolve(['email' => 'this is not an email']));
   } catch (ResolverException $e) {
       echo $e->getMessage(), "\n";
   }

Output:

::

  Failed to resolve options due to following errors:

  1) email: must be a valid email address


.. NOTE::

   To validate all options at the root level, define one or more validators
   using ``$resolver->addValidator()``.

.. TIP::

   It is possible to pass additional arguments to all normalizers and validators.
   See `Resolver context`_.


Supported types
---------------

- ``NULL`` - any type
- ``"bool"``
- ``"int"``
- ``"float"``
- ``"number"`` - integer or float
- ``"numeric"`` - integer, float or a numeric string
- ``"string"``
- ``"array"``
- ``"iterable"`` - array or an instance of ``Traversable``
- ``"object"``
- ``"resource"``
- ``"scalar"`` - integer, float, string or a boolean
- ``"callable"``

Any other type is considered to be a class name, accepting instances of the given
class or interface (or their descendants).

An option defined as nullable will also accept a ``NULL`` value. See `nullable()`_.


Normalizer and validator value types
------------------------------------

The type of the value passed to normalizers and validators depend on the type
of the option.

- ``Option::list()``, ``Option::choiceList()`` - an array of values
- ``Option::node()`` - a ``Node`` instance
- ``Option::nodeList()`` - an array of ``Node`` instances
- other - depends on the type of the option (``string``, ``int``, etc.)

.. NOTE::

   A normalizer may modify or replace the value (including its type) before
   it is passed to subsequent normalizers and validators.


Node options
------------

Node options accept an array of the specified options. With them it is possible
to resolve more complex structures.

- node options are resolved iteratively (without recursion)
- certain configuration behaves differently with node options, see `Option configuration`_

.. code:: php

   <?php

   use Kuria\Options\Option;
   use Kuria\Options\Resolver;

   $resolver = new Resolver();

   $resolver->addOption(
       Option::string('username'),
       Option::node(
           'personalInformation',
           Option::int('birthYear'),
           Option::int('height')->default(null),
           Option::float('weight')->default(null)
       ),
       Option::nodeList(
           'securityLog',
           Option::string('action'),
           Option::int('timestamp'),
           Option::node(
               'client',
               Option::string('ip'),
               Option::string('userAgent')
           )
       )
   );


Handling validation errors
==========================

The ``Resolver->resolve()`` method throws ``Kuria\Options\Exception\ResolverException``
on failure.

The specific errors can be retrieved by calling ``getErrors()`` on the exception object.


.. code:: php

   <?php

   use Kuria\Options\Resolver;
   use Kuria\Options\Exception\ResolverException;
   use Kuria\Options\Option;

   $resolver = new Resolver();

   $resolver->addOption(
       Option::string('name'),
       Option::int('level'),
       Option::int('score')
   );

   try {
       $resolver->resolve([
           'name' => null,
           'level' => 'not_a_string',
           'foo' => 'bar',
       ]);
   } catch (ResolverException $e) {
       foreach ($e->getErrors() as $error) {
           echo $error->getFormattedPath(), "\t", $error->getMessage(), "\n";
       }
   }

Output:

::

  name    string expected, but got NULL instead
  level   int expected, but got "not_a_string" instead
  score   this option is required
  foo     unknown option


Ignoring unknown keys
=====================

The ``Resolver`` can be configured to ignore unknown keys by calling
``$resolver->setIgnoreUnknown(true)``.

- ``UnknownOptionError`` will no longer be raised for unknown keys
- this applies to nested options as well
- the unknown keys will be present among the resolved options


Integrating the options resolver
================================

The ``StaticOptionsTrait`` can be used to easily add static option support
to a class.

It has the added benefit of caching and reusing the resolver in multiple
instances of the class. If needed, the cache can be cleared by calling
``Foo::clearOptionsResolverCache()``.

.. code:: php

   <?php

   use Kuria\Options\Integration\StaticOptionsTrait;
   use Kuria\Options\Node;
   use Kuria\Options\Option;
   use Kuria\Options\Resolver;

   class Foo
   {
       use StaticOptionsTrait;

       /** @var Node */
       private $config;

       function __construct(array $options)
       {
           $this->config = static::resolveOptions($options);
       }

       protected static function defineOptions(Resolver $resolver): void
       {
           $resolver->addOption(
               Option::string('path'),
               Option::bool('enableCache')->default(false)
           );
       }

       function dumpConfig(): void
       {
           var_dump($this->config);
       }
   }

Instantiation example:

.. code:: php

   <?php

   $foo = new Foo(['path' => 'file.txt']);

   $foo->dumpConfig();

Output:

::

  object(Kuria\Options\Node)#8 (2) {
    ["path"]=>
    string(8) "file.txt"
    ["enableCache"]=>
    bool(false)
  }
