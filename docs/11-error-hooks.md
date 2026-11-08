<!-- nav-group: Controlling the flow -->
# Reacting to failures

How do I run my own code when a step fails: `onError()` and `onFatal()`?

Hubs and managers both take two hooks, each called with the envelope and the exception:

- `onError(fn ($subject, $exception) => …)` runs for every `error()` and unexpected exception a pipe records, and then the next pipe runs.
- `onFatal(fn ($subject, $exception) => …)` runs when a fatal stops things, with the `PipeRuleFatal`.

## onError()

A hub's hook hears about its own pipes; a manager's hears about every hub's, after the hub's own hook.

## onFatal()

A hub's hook runs when a fatal stops that hub, whether it is contained or not; a manager's runs when a fatal stops the whole flow (not for one contained to its hub), after the hub's. Both run before `sendIt()` returns or throws, so what they log is saved with the rest. An error that `errorsAreFatal()` turns into a fatal goes to `onFatal()`, not `onError()`, and the fatal's `getPrevious()` is the original error.

## Recipe: notify someone on a fatal

Put the hook on the manager, so it fires once, when the whole flow stops. Inside it, log what you did, so the notification is part of the saved run:

- `->onFatal(fn ($post, $fatal) => …)` receives the envelope and the `PipeRuleFatal`; `$fatal->getMessage()` is the reason.
- Send your notification, and log it with `$post->logger()->info('Notify', 'Told the on-call')`.
- Keep the hook from throwing: an exception it throws changes what happens next (see below).

The last example on [Redirects and views](12-redirects-and-views.md) runs a manager `onFatal()` hook and prints what it received.

## What a hook may throw

What a hook throws decides what happens next:

- A redirect or view is handed off and leaves `sendIt()` (see [Redirects and views](12-redirects-and-views.md)). A `PipeHubException` leaves `sendIt()` as a configuration error, from either hook. In both cases that exception, not a fatal, is what reaches your code.
- A `PipeRuleFatal` thrown by `onError()` escalates the error: it is recorded and stops things like any fatal.
- Any other exception from `onError()` means the hub cannot carry on, so it stops as for a fatal wrapping that exception.
- Any other exception from `onFatal()` is recorded as a fatal and the stop carries on exactly as it would have.

`then()` callbacks are handled like pipes: an exception thrown in one is an error (recorded, sent to `onError()`, and the flow carries on), or a fatal under the hub's `errorsAreFatal()`, and a `PipeRuleFatal` thrown there stops things like any fatal. If a `skipIf()` callback or an event listener throws, the hub cannot carry on either, so it stops as for a fatal, with the exception as the fatal's `getPrevious()`.

Next: [Redirects and views](12-redirects-and-views.md).
