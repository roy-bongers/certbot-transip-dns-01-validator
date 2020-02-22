# Providers
Currently there is only one provider you can use which is `transip`. The project is setup so it's easy to add additional
providers.

## Adding a new provider
Adding a provider is made quite easy. Once you have everything running you can create a pull request with your changes.

### Creating a provider
Create a class in the `src/CertbotDns01/Providers/` directory and make sure it extends the `ProviderInterface`.
If you need an external package you can install with via composer. I highly recommend using the file
`src/CertbotDns01/Providers/TransIp.php` as a reference.

The interface requires three methods:
* `createChallengeDnsRecord` this should create the needed DNS record via an API of your hosting provider.
* `cleanChallengeDnsRecord` this method should remove the DNS record after it has been validated.
* `getDomainNames` should return a simple array with the domain names that can be managed via de API.

Both the create and clean methods take a `ChallengeRecord` object as parameter. This object contains the validation
string, the domain the request is for and the DNS record name (_acme-challenge.subdomain).

If you need additional objects you can include them in the constructor of your provider and they will be loaded
automatically via dependency injection. You should at least load the `Config` and `Logger` class so you fetch
credentials from the config file / `ENV` variables and log any errors / debug info.

If you need help, feel free to create an issue with a link to your current source and a description of your problem.

### Unit tests
When everything is running I would highly recommend creating a unit test. You can mock any results you receive from the
provider's API.
