import { queryParams, type RouteQueryOptions, type RouteDefinition, applyUrlDefaults } from './../../../../wayfinder'
/**
* @see \App\Http\Controllers\LeaveAttachmentController::download
 * @see app/Http/Controllers/LeaveAttachmentController.php:16
 * @route '/leave-attachments/{attachment}'
 */
export const download = (args: { attachment: number | { id: number } } | [attachment: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: download.url(args, options),
    method: 'get',
})

download.definition = {
    methods: ["get","head"],
    url: '/leave-attachments/{attachment}',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\LeaveAttachmentController::download
 * @see app/Http/Controllers/LeaveAttachmentController.php:16
 * @route '/leave-attachments/{attachment}'
 */
download.url = (args: { attachment: number | { id: number } } | [attachment: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { attachment: args }
    }

            if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
            args = { attachment: args.id }
        }
    
    if (Array.isArray(args)) {
        args = {
                    attachment: args[0],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        attachment: typeof args.attachment === 'object'
                ? args.attachment.id
                : args.attachment,
                }

    return download.definition.url
            .replace('{attachment}', parsedArgs.attachment.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\LeaveAttachmentController::download
 * @see app/Http/Controllers/LeaveAttachmentController.php:16
 * @route '/leave-attachments/{attachment}'
 */
download.get = (args: { attachment: number | { id: number } } | [attachment: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: download.url(args, options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\LeaveAttachmentController::download
 * @see app/Http/Controllers/LeaveAttachmentController.php:16
 * @route '/leave-attachments/{attachment}'
 */
download.head = (args: { attachment: number | { id: number } } | [attachment: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: download.url(args, options),
    method: 'head',
})
const LeaveAttachmentController = { download }

export default LeaveAttachmentController