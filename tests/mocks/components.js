/**
 * Mock for @wordpress/components
 */
import React from 'react';

export const Button = ({ children, onClick, isPrimary, isSecondary, isBusy, ...props }) => (
    <button onClick={onClick} data-primary={isPrimary} data-secondary={isSecondary} {...props}>
        {children}
    </button>
);

export const TextControl = ({ label, value, onChange, ...props }) => (
    <div>
        <label>{label}</label>
        <input
            type="text"
            value={value || ''}
            onChange={(e) => onChange(e.target.value)}
            {...props}
        />
    </div>
);

export const ToggleControl = ({ label, checked, onChange, help }) => (
    <div>
        <label>
            <input
                type="checkbox"
                checked={checked}
                onChange={(e) => onChange(e.target.checked)}
            />
            {label}
        </label>
        {help && <p>{help}</p>}
    </div>
);

export const Spinner = () => <span data-testid="spinner">Loading...</span>;

export const Notice = ({ children, status, onRemove }) => (
    <div data-status={status} role="alert">
        {children}
        {onRemove && <button onClick={onRemove}>Dismiss</button>}
    </div>
);

export const TabPanel = ({ tabs, children }) => (
    <div>
        {tabs.map(tab => (
            <button key={tab.name}>{tab.title}</button>
        ))}
        {children && children(tabs[0])}
    </div>
);

export const Panel = ({ children }) => <div>{children}</div>;
export const PanelBody = ({ children, title }) => <div><h3>{title}</h3>{children}</div>;
export const ComboboxControl = ({ value, onChange, options }) => (
    <select value={value} onChange={(e) => onChange(e.target.value)}>
        {options?.map(opt => <option key={opt.value} value={opt.value}>{opt.label}</option>)}
    </select>
);
