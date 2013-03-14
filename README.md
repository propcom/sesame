# Sesame

Sesame is an Authentication package for FuelPHP. It was created because I didn't understand Fuel's own Auth package, and
an alternative to Auth is requested frequently enough to matter.

## Usage

Sesame aliases the class `\Sesame\Sesame` into the root namespace.

Using Sesame is simple because the concept of auth is considered to have only two functions:

* Authenticating a user 
* Authorising a user

To get the most basic functionality you can simply:

* Load the Sesame package 
* Load the Sesame module 
* Add the `/login` route to go to `/sesame/login/login`

Since this does not capture every request and check for logged-in-ness you should also create a `before()` method on
some base controller that uses `\Sesame::instance->user()` and `\Sesame::instance()->login()`:

    \Sesame::instance()->user() or \Sesame::instance()->login();

### Configurable things

This simply lists the Config settings that Sesame will look for at one point or another

* `sesame.drivers.$driver` - Class name for the driver `$driver` 
* `sesame.password.hash_fn` - Hashing function used in `Sesame\Util::hash()`
* `sesame.password.salt` - Salt if required by hash function
* `sesame.template` - Template to use for default login view

### Instances

Since FuelPHP does it and we're going for consistency, Sesame works on an instance basis.

* If you call `Sesame::instance()` you will get an instance of the Sesame class configured with the 'default' 
configuration.
* If you call `Sesame::instance('config')` then it will try to load `sesame.config` from Config, and the login driver
will be `sesame.config.login_driver`. `config` here is of course a placeholder for whatever you call it.
* If you call `Sesame::instance('Class\Name')` then it will use `\Class\Name` as the login driver. If it is ambiguous,
it will first try to find config by the string name, and then try a class by the string name, and then bail.

## Authentication

### Users

The central concept in Sesame is "a user". Sesame doesn't care what you mean by "a user", except to state that it must
have a way of identifying it singly. That is to say, your model must have at least one unique key. Sesame doesn't even
care whether the user has or does not have a password; all _you_ do is tell it how to find a user later.

Usually a user will pass at least two pieces of information; at least one identifier and at least one credential. The
most obvious example is password authentication: a user is identified by their username and proven by their password.
Sesame makes no assumptions about how you prove that a user is who they say they are; you could redirect the user to
OpenID for all Sesame cares. All you have to do is respond accurately when Sesame asks your Login driver to log a user
in.

Sesame doesn't make any assumptions about your user model because it doesn't know what model you are using. Instead, you
configure a login driver, and it is the login driver (and any module- or app-specific controllers) that interface with
the user model directly.

This puts a lot more onus on you to write a sensible driver.

### Login

In order to avoid crappy configuration stuff all the time, Sesame uses a driver system that interjects itself between
Auth and the User model. You can configure that, or you can specify which driver you want to use explicitly at runtime.

#### Login Driver

Because the driver is so thin, it is encouraged to write app-specific drivers. The driver has three required static
methods. 

1. `login`. It takes no parameters. Sesame will call this method on your login driver when it discovers no user is
currently logged in. 

2. `retrieve_user`. This takes one parameter, which is whatever you give to Sesame. Sesame uses this to retrieve the
user from the session. Since you can identify users in any way you like, when you have authenticated a user you should
provide Sesame with the information you accept in `retrieve_user`. See "Logging in" below

3. `make_user`. This is sent user data. You will have collected this data somehow or other so Sesame just passes it on.
It should return the created user on success, or any false value on failure.

A simple driver implementation follows:

    class Login_Driver 
    { 
        public static function login() 
        { 
            \Response::redirect('/login'); 
        }

        public static function retrieve_user($user_id) 
        { 
            return Model_User::find($user_id); 
        }

        public static function make_user($user_data)
        {
            // Probably better to do something like Model_User::signup($user_data).
            // This is just an example.
            $u = Model_User::forge();
            $u->username = $user_data['username'];
            $u->password = \Sesame\Util::hash($user_data['password']);
            $u->save();

            return $u;
        }
    }

`login` is an obvious place to redirect users to an external service, or basically to do whatever. The builtin login
driver (`Sesame\Driver_Default`) does this, but also stashes the originally-requested URI in the session for later use.

#### Logging in

Sesame requires you to tell it when login has succeeded. To do this, you pass Sesame enough information to identify the
user. In the background Sesame will add this information to the session, and use it in future requests to grab the user
again.

This data can be given to Sesame in one of two ways:

* If the request has to end, call `Sesame::instance()->user_ok` with the data once you have authorised the user.
* If the user can be authorised within the same request, simply return the data from the `login` method.

Remember, that data will be stored in the session and used each request to retrieve the user, so make sure that you
provide Sesame with enough information to retrieve your user. Sesame will pass the exact same data back to
`retrieve_user` on your driver, so it's on you to pass data you want to see.

The default controller, driver, and user model in the Sesame module should be well-commented enough to explain the
procedure and get you running to write your own drivers.

#### Logging out

Simply call `Sesame::instance()->logout()`. This will clear the session and unset the user. You probably will want to
force a redirect immediately afterward.

#### Signing up

The Sesame instance provides a `signup()` method, to which you can pass any user data required to create a user. This
will be directly passed to the `make_user` method of your driver, so really this is just a convenient way of deciding
which driver it goes to by getting Sesame to do it.

### Extending Sesame

The Sesame class uses `static::` and `$this->` in all situations, meaning it can be extended and overridden without
concern.

## ACL

ACL is a helper class in Sesame that associates user permissions with URIs. User permissions are expected to be
associated with users in the driver's own namespace; this is enforced by presuming the User class you use has a
`has_permission` method on it. This method takes solely the string name of the permission to test for and returns a
boolean value as appropriate.

The flexibility of this system is that you define what a permission is, what the string name means, and how to determine
whether it is associated with a given user. The ACL's job is only to associate these strings with URI paths, then
interrogate the user object later to determine access. You can even forgo the whole idea if you make sure never to pass
string values to the ACL.

The ACL class is also aliased into the root namespace.

### Access rules

#### URIs

The URIs you give to ACL are the root-based path part of the URI within your site. As such they always should start with
`/`; but if they don't, one will be added, so relative paths are assumed to be relative to root and, hence, absolute.

Paths are divided into segments and each segment can have zero or more rules of the same type associated with it. It
cannot have two types associated with it.

To test a path, call either `check_access` or `check_user_access` statically on the ACL object. The former takes the
path as a parameter, and the latter takes the user and then the path. The former simply gets the current user out of the
default Sesame driver and calls the other.

The provided path is then matched against the ones with rules. A match is taken by specificity, i.e. the longest defined
path that matches at the start of the provided one. This means you can set up a rule for an entire section of the site,
e.g. `/user`, as requiring a permission, and then an individual path like `/user/login` to override that.

#### Types

There are four available access rule types:

* `allow_if`
* `deny_if`
* `allow_unless`
* `deny_unless`

These differ in subtle ways:

* `_if` rules require _any_ rule to pass; 
* `_unless` requires _all_ rules to pass;
* `_if` rules imply the `allow` or `deny` is the exception - the _opposite_ is the default;
* `_unless` rules imply the `allow` or `deny` is the _default_ - the opposite only happens when _all_ rules are met.

Explicitly:

* `allow_if` denies by default, and allows if _any_ rule is met;
* `deny_if` allows by default, and denies if _any_ rule is met;
* `allow_unless` allows by default, and denies if _all_ rules are met;
* `deny_unless` denies by default, and allows if _all_ rules are met.

*Important:* Realise that if you add `_unless` rules to an existing set, it still has to match all of them; the original
set is extended, so even if you pass all of the original set you still have to pass the extras.

Code example!

	\ACL::allow_if('/restricted-path', [ 'admin' ]);
	\ACL::deny_if('/', [ 'banhammer' ]);

	// ... later ...
	if (\ACL::check_user_access($user, $url)) 
	{
		// as you were
	}

Each URI can only be associated with one rule type, which can contain an array of any number of actual rules. You can call
the same method with the same URI any number of times and the list of rules will be extended, but it is an exception to
try to later set a rule of a different type on a URI already registered.

You can define ACL rules from anywhere; but you will probably do it in your app bootstrap. Modules can add paths to your
app but do not have the bootstrap concept; and packages have bootstraps but don't define paths. Further, a module won't
know what permissions you have set up - and can't even assume that you are using permissions in the first place - so it
is best left to the app to define access rules.

The action you apply to the root path (probably something you'll do in your app bootstrap) determines the default action
to take when no rules match, since this is the least specific rule and will always match. In the above example,
`deny_if` implies the default action is _allow_; denial is only the case _iff_ one of the rules matches. However, if the
path is `/restricted-path`, denial is the default action because we have set up an `allow_if` rule on it, which will
allow access _iff_ one of the rules passes.

If you don't set up a root rule and no rule matches, the default behaviour is to deny.

#### Rules

Rules are defined in one of three ways:

* Strings are treated as permission names and are passed to `has_permission` on the user object being interrogated
  * Strings starting with `~` mean "not this permission" - this is useful for creating e.g. a 'banned' permission
* Functions are run with the user object
* The exact values `true` and `false` can be used instead of writing a function that always returns true or false. This
can be useful to make the root path a deny path, and to allow access to certain more specific paths like login.

ACL does _not_ use `is_callable` because it is a travesty. Functions _must_ be instances of `Closure` to be run. This
avoids problems where a permission is happenstantially named the same as a function, and thus looks callable.

To be treated as a permission, the string is passed to the User object's `has_permission` method. If the User class
you're using has no such function you will receive a runtime error because you're doing it wrong.

#### Fallthrough

By default rules do not fall through; that means that if a specific match is made, parent paths will not be tested. In
any rule creation you can request fallthrough by adding an extra boolean parameter to the method call. When true, a
function will be created that returns the result of checking access on the parent path.

This function still follows the `_if`/`_unless` conditions; so if you request fallthrough on an `_unless` rule, the user
has to both be allowed by the specified rules _and_ by the parent rules; whereas if you request fallthrough on an `_if`
rule, you are allowed in if these rules _or_ the parent rules say so.

Here's an example.

    // Allow access if the user is in the user group.
    \ACL::allow_if('/user', ['user']);

    // Allow access if the user is in the admin group _or_ in any rules set on /user
    \ACL::allow_if('/user/edit', ['admin'], true);

    // Allow access only if the user is in the internal _and_ marketing groups
    \ACL::deny_unless('/marketing', ['internal', 'marketing']);

    // Allow access only if the user is in the email group _and_ is allowed to access /marketing
    \ACL::deny_unless('/marketing/email', ['email'], true);

By doing this you can easily create:

* Areas of the site that require a specific credential, and then subsections thereof that can also be accessed by
people with a different credential
* Areas of the site denied to people with a specific credential, and subsections that are only denied if they have
another one as well
* Areas of the site that require a specific credential, with subsections that require even more credentials
* Areas of the site denied to people with a specific credential, but subsections that can be accessed if you have a
different (overriding) one

I only exemplified two of these here; those seem the most useful. Experiment with the combinations of `_if` and
`_unless` when fallthrough is on.

It is an exception to require fallthrough on the root path.

##### Default fallthrough

Fallthrough is off by default, but you can call `\ACL::fallthrough(true)` to turn it on. In this case, you can pass
`false` to your rules to explicitly turn it off for those.

If you turn fallthrough on by default you won't be penalised for not passing `false` for the root one.

#### `can_access`

There is a trait `\Sesame\ACL_User` containing a single method that you can install into your user class, allowing

	$user->can_access($url)

from anywhere in your code.
