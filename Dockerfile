FROM "php:7.3-cli"

# Install composer
COPY --from=composer /usr/bin/composer /usr/bin/composer

# Make sure apt is up to date
RUN \
	apt-get "update"

# Install Intl
RUN \
	apt-get -y install "libicu-dev" && \
	docker-php-ext-install "intl"

# Install Gettext
RUN \
	docker-php-ext-install "gettext"

# Install MySQLi
RUN \
	docker-php-ext-install "mysqli"

RUN \
	pecl "install" "redis" && \
	docker-php-ext-enable "redis"

# Copy the application
WORKDIR "/app"
COPY "." "/app"

# Update the composer
RUN \
	cd "/app" \
	composer "update"

# Make the template directory
RUN \
	cd "/app" \
	mkdir "templates_c"
