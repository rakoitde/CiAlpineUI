<script>
     document.addEventListener('alpine:init', () => {

        Alpine.magic('cell', (el, { Alpine }) => {

            if (!el.__cellTimers) el.__cellTimers = new Set();

            return new Proxy(Alpine.$data(el), {
                
                get(_, method) {
                    if (method in _ && typeof _[method] !== 'function') return _[method];
                    
                    const callBackend = async (...args) => {

                        let meta = {};
                        if (args[0] && typeof args[0] === 'object' && (args[0].skip || args[0].only)) {
                            meta = args.shift();
                            if (meta.skip && meta.only) {
                                throw new Error('The parameters "skip" and "only" cannot be used simultaneously.');
                            }
                        }

                        // --- convert FormData in Object ---
                        args.forEach((arg, index) => {
                            if (arg?.tagName === 'FORM') {
                                args[index] = Object.fromEntries(new FormData(arg));
                            }
                        });

                        const componentData = Alpine.$data(el);

                        const oldData = JSON.parse(JSON.stringify(componentData));

                        const root = Alpine.closestRoot(el);
                        const componentId = root.getAttribute('x-id') || null;
                        const componentAttr = root.getAttribute('x-component');
                        if (!componentAttr) throw new Error('Missing x-component attribute in root element');
                        const componentName = componentAttr.replaceAll('\\', '/');

                        let filteredData = {};
                        if (meta.only) {
                            for (const key of meta.only) {
                                if (key in oldData) filteredData[key] = oldData[key];
                            }
                        } else {
                            filteredData = { ...oldData };
                            if (meta.skip) {
                                for (const key of meta.skip) delete filteredData[key];
                            }
                        }

                        const body = {
                            component: { id: componentId, name: componentName },
                            data: filteredData,
                            request: { action: method, params: args },
                        };

                        const url = 'component';
                        const response = await fetch(url, {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify(body),
                        });

                        if (!response.ok) {
                            const text = await response.text();

                            // window.addEventListener('cell:http-error', e => {
                            //     const { status, message } = e.detail;
                            //     console.log('Global Error Handler:', status, message);
                            //     if (status === 401) location.href = '/login';
                            // });

                            window.dispatchEvent(new CustomEvent('cell:http-error', {
                                detail: { status: response.status, message: text }
                            }));
                            
                            throw new Error(`Server responded with ${response.status}: ${text}`);
                        }

                        const result = await response.json();

                        if (result.html) {

                            if (el.__cellTimers) {
                                for (const t of el.__cellTimers) clearInterval(t);
                                el.__cellTimers.clear();
                            }
                            root.outerHTML = result.html;
                            return;

                        } 
                        
                        for (const key in result) {
                            if (typeof componentData[key] !== 'function') {
                                componentData[key] = result[key];
                            }
                        }

                        return result;
                    };

                    const cellProxy = new Proxy(callBackend, {
                        get(target, prop) {

                            if (prop === 'interval') {
                    
                                    return (delay, ...args) => {
                                        const ms = parseInt(delay);
                                    if (isNaN(ms)) throw new Error(`Invalid interval delay: ${delay}`);

                                        const timer = setInterval(() => target(...args), ms);

                                        el.__cellTimers.add(timer);

                                    if (typeof $cleanup === 'function') {
                                            $cleanup(() => {
                                                clearInterval(timer);
                                                el.__cellTimers.delete(timer);
                                            });
                                        } else {
                                            el.addEventListener('destroy', () => {
                                                clearInterval(timer);
                                                el.__cellTimers.delete(timer);
                                            });
                                        }

                                        return timer;
                                    };
                            }

                            return target[prop];
                        },
                    });

                    return cellProxy;
                },
            });
        });
    });
 </script>