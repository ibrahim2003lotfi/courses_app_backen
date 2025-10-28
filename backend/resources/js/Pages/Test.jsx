import React from 'react';

export default function Test({ message }) {
    return (
        <div style={{ padding: '50px', textAlign: 'center' }}>
            <h1 style={{ fontSize: '32px', color: 'blue' }}>
                {message}
            </h1>
        </div>
    );
}