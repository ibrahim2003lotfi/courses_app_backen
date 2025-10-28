import React from 'react';

class ErrorBoundary extends React.Component {
    constructor(props) {
        super(props);
        this.state = { hasError: false, error: null };
    }

    static getDerivedStateFromError(error) {
        return { hasError: true, error };
    }

    componentDidCatch(error, errorInfo) {
        console.error('Error caught by boundary:', error, errorInfo);
        
        // You can log the error to an error reporting service here
        // logErrorToService(error, errorInfo);
    }

    render() {
        if (this.state.hasError) {
            return (
                <div className="min-h-screen flex items-center justify-center bg-gray-100">
                    <div className="max-w-md w-full bg-white rounded-lg shadow-lg p-6">
                        <h2 className="text-2xl font-bold text-red-600 mb-4">Oops! Something went wrong</h2>
                        <p className="text-gray-600 mb-4">
                            We're sorry, but something went wrong. Please try refreshing the page.
                        </p>
                        <button
                            onClick={() => window.location.reload()}
                            className="bg-blue-500 text-white px-4 py-2 rounded hover:bg-blue-600"
                        >
                            Refresh Page
                        </button>
                        {process.env.NODE_ENV === 'development' && (
                            <details className="mt-4">
                                <summary className="cursor-pointer text-sm text-gray-500">
                                    Error details (Development only)
                                </summary>
                                <pre className="mt-2 text-xs bg-gray-100 p-2 rounded overflow-auto">
                                    {this.state.error?.toString()}
                                </pre>
                            </details>
                        )}
                    </div>
                </div>
            );
        }

        return this.props.children;
    }
}

export default ErrorBoundary;