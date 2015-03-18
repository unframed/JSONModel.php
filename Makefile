DEPS = deps \
	deps/test-more-php \
	deps/test_sites.php \
	deps/JSONMessage.php \
	deps/SQLAbstract.php \
	deps/WordPress

test: pull test_php

test_php:
	./press up wp
	php test/create.php
	php test/insert.php
	php test/update.php
	./press down wp

pull: ${DEPS}
	cd deps/JSONMessage.php && git pull origin
	cd deps/SQLAbstract.php && git pull origin
	cd deps/WordPress && git pull origin

deps:
	mkdir -p deps

deps/test-more-php:
	svn checkout http://test-more-php.googlecode.com/svn/trunk/ deps/test-more-php

deps/test_sites.php:
	git clone \
		https://github.com/unframed/test_sites.php \
		deps/test_sites.php

deps/JSONMessage.php:
	git clone \
		https://github.com/laurentszyster/JSONMessage.php.git \
		deps/JSONMessage.php

deps/SQLAbstract.php:
	git clone \
		https://github.com/unframed/SQLAbstract.php.git \
		deps/SQLAbstract.php

deps/WordPress:
	git clone \
		https://github.com/WordPress/WordPress.git \
		deps/WordPress